<?php

declare(strict_types=1);

namespace App\Services\UniMice;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Единственная точка HTTP-общения с REST API платформы UniMICE.
 *
 * Отвечает за: логин сервисной учётки, кеширование JWT, обновление
 * токена при 401 и базовые настройки HTTP. Разбор тела ответа
 * (конверт, DTO) — задача выше лежащих слоёв.
 */
class UniMiceClient
{
    private readonly string $baseUrl;
    private readonly string $loginPath;
    private readonly ?string $email;
    private readonly ?string $password;
    private readonly int $timeout;
    private readonly int $connectTimeout;
    private readonly int $retryTimes;
    private readonly int $tokenCacheTtl;
    private readonly string $tokenCacheKey;

    public function __construct()
    {
        $baseUrl = config('unimice.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new RuntimeException('UniMICE API base_url is not configured (UNIMICE_API_BASE_URL)');
        }

        $this->baseUrl = rtrim($baseUrl, '/');
        $this->loginPath = (string) config('unimice.auth.login_path', '/auth/login');
        $this->email = config('unimice.auth.email');
        $this->password = config('unimice.auth.password');
        $this->timeout = (int) config('unimice.http.timeout', 120);
        $this->connectTimeout = (int) config('unimice.http.connect_timeout', 10);
        $this->retryTimes = (int) config('unimice.http.retry_times', 2);
        $this->tokenCacheTtl = (int) config('unimice.token.cache_ttl', 72000);
        $this->tokenCacheKey = (string) config('unimice.token.cache_key', 'unimice.api.token');
    }

    /**
     * JWT для авторизации. По умолчанию — из кеша, иначе логин в API.
     * $fresh = true принудительно перелогинивается (например, после 401).
     */
    public function token(bool $fresh = false): string
    {
        if ($fresh) {
            Cache::forget($this->tokenCacheKey);
        }

        return (string) Cache::remember(
            $this->tokenCacheKey,
            $this->tokenCacheTtl,
            fn (): string => $this->login(),
        );
    }

    /**
     * Авторизованный запрос к API. При 401 обновляет токен и повторяет
     * запрос один раз. Возвращает "сырой" ответ — разбор выше.
     *
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $body
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null): Response
    {
        $response = $this->send($method, $path, $query, $body);

        if ($response->status() === 401) {
            $response = $this->http($method, $this->token(fresh: true))
                ->send($method, $this->url($path), $this->options($query, $body));
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $body
     */
    private function send(string $method, string $path, array $query, ?array $body): Response
    {
        return $this->http($method)->send($method, $this->url($path), $this->options($query, $body));
    }

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    private function options(array $query, ?array $body): array
    {
        $options = [];

        if ($query !== []) {
            $options['query'] = $query;
        }

        if ($body !== null) {
            $options['json'] = $body;
        }

        return $options;
    }

    private function http(string $method, ?string $token = null): PendingRequest
    {
        $request = Http::connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->acceptJson()
            ->asJson()
            ->withToken($token ?? $this->token());

        // Ретраим только идемпотентные методы: повтор не-GET запроса после
        // обрыва соединения может создать дубль сущности на бэкенде.
        if ($this->isIdempotent($method)) {
            // throw: false — ошибочные HTTP-ответы возвращаются как Response,
            // их разбирает слой выше; вылетать должны только обрывы соединения.
            $request->retry(
                $this->retryTimes,
                1000,
                fn (Throwable $e): bool => $e instanceof ConnectionException,
                throw: false,
            );
        }

        return $request;
    }

    private function isIdempotent(string $method): bool
    {
        return in_array(strtoupper($method), ['GET', 'HEAD', 'OPTIONS'], true);
    }

    private function url(string $path): string
    {
        return $this->baseUrl.($path === '' ? '' : '/'.ltrim($path, '/'));
    }

    /**
     * Логин сервисной учётки. Ответ логина — "плоский"
     * {"token": "...", "userSession": {...}}, без стандартного конверта.
     */
    private function login(): string
    {
        if (! is_string($this->email) || $this->email === '' || ! is_string($this->password) || $this->password === '') {
            throw new RuntimeException('UniMICE API credentials are not configured (UNIMICE_API_EMAIL / UNIMICE_API_PASSWORD)');
        }

        // Логин идемпотентен (сущностей не создаёт), поэтому обрыв
        // соединения можно безопасно ретраить.
        $response = Http::connectTimeout($this->connectTimeout)
            ->timeout($this->timeout)
            ->retry(
                $this->retryTimes,
                1000,
                fn (Throwable $e): bool => $e instanceof ConnectionException,
                throw: false,
            )
            ->acceptJson()
            ->post($this->url($this->loginPath), [
                'email' => $this->email,
                'password' => $this->password,
            ]);

        $token = $response->json('token');

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('UniMICE login failed: HTTP '.$response->status());
        }

        return $token;
    }
}
