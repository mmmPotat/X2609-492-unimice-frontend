<?php

declare(strict_types=1);

namespace App\Services\UniMice;

use Illuminate\Http\Client\Response;

/**
 * Конверт ответа API UniMICE: {"status", "data", "message", "errors", "timestamp"}.
 *
 * Исключение — логин (/auth/login): он отвечает "плоским" {token, userSession}
 * и разбирается в UniMiceClient напрямую, без этого класса.
 */
final class ApiResponse
{
    /**
     * @param array<string, list<string>>|null $errors ошибки валидации по полям
     */
    public function __construct(
        public readonly string $status,
        public readonly mixed $data,
        public readonly ?string $message,
        public readonly ?array $errors,
        public readonly ?string $timestamp,
        public readonly int $httpStatus,
    ) {}

    public static function fromHttp(Response $response): self
    {
        /** @var array<string, mixed> $json */
        $json = $response->json() ?? [];

        return new self(
            status: (string) ($json['status'] ?? ($response->successful() ? 'success' : 'error')),
            data: $json['data'] ?? null,
            message: isset($json['message']) ? (string) $json['message'] : null,
            errors: self::normalizeErrors($json['errors'] ?? null),
            timestamp: isset($json['timestamp']) ? (string) $json['timestamp'] : null,
            httpStatus: $response->status(),
        );
    }

    public function isSuccess(): bool
    {
        return $this->httpStatus < 400 && $this->status === 'success';
    }

    public function isError(): bool
    {
        return ! $this->isSuccess();
    }

    /**
     * Исключение, если ответ неуспешен, иначе null.
     */
    public function toException(): ?UniMiceApiException
    {
        if ($this->isSuccess()) {
            return null;
        }

        return new UniMiceApiException(
            message: $this->message ?? 'UniMICE API request failed: HTTP '.$this->httpStatus,
            httpStatus: $this->httpStatus,
            errors: $this->errors,
            apiMessage: $this->message,
        );
    }

    /**
     * @return array<string, list<string>>|null
     */
    private static function normalizeErrors(mixed $errors): ?array
    {
        if (! is_array($errors)) {
            return null;
        }

        $normalized = [];

        foreach ($errors as $field => $messages) {
            $normalized[(string) $field] = array_values(array_map(
                static fn (mixed $m): string => (string) $m,
                is_array($messages) ? $messages : [$messages],
            ));
        }

        return $normalized === [] ? null : $normalized;
    }
}
