<?php

declare(strict_types=1);

namespace Tests\Feature\Services\UniMice;

use App\Services\UniMice\UniMiceClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

final class UniMiceClientTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'unimice.base_url' => 'https://api.unimice.test',
            'unimice.auth.login_path' => '/auth/login',
            'unimice.auth.email' => 'bot@example.com',
            'unimice.auth.password' => 'secret',
        ]);

        Cache::flush();
    }

    public function test_login_reads_flat_token_response(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc', 'userSession' => ['id' => 1]]),
        ]);

        $this->assertSame('jwt-abc', (new UniMiceClient())->token());
    }

    public function test_login_failure_throws(): void
    {
        Http::fake(['*/auth/login' => Http::response(['message' => 'Invalid credentials.'], 401)]);

        $this->expectException(RuntimeException::class);

        (new UniMiceClient())->token();
    }

    public function test_token_is_cached_and_sent_as_bearer(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/events' => Http::response(['status' => 'success', 'data' => ['events' => []]]),
        ]);

        $client = new UniMiceClient();

        $client->token();
        $client->token();
        $response = $client->request('GET', '/events');

        // логин ровно один раз — второй token() взят из кеша
        Http::assertSentCount(2);

        Http::assertSent(static function ($request) {
            return str_contains((string) $request->url(), '/events')
                && $request->hasHeader('Authorization', 'Bearer jwt-abc');
        });

        $this->assertSame(200, $response->status());
    }

    public function test_401_refreshes_token_and_retries_once(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-new']),
            '*/events' => Http::sequence()
                ->push(['status' => 'error', 'message' => 'Expired'], 401)
                ->push(['status' => 'success', 'data' => ['events' => [['id' => 1]]]]),
        ]);

        $response = (new UniMiceClient())->request('GET', '/events');

        $this->assertSame(200, $response->status());
        $this->assertCount(1, $response->json('data.events'));

        // после 401 токен в кеше заменён на свежий
        $this->assertSame('jwt-new', Cache::get('unimice.api.token'));

        Http::assertSent(static function ($request) {
            return str_contains((string) $request->url(), '/events')
                && $request->hasHeader('Authorization', 'Bearer jwt-new');
        });
    }

    public function test_missing_base_url_throws_clear_error(): void
    {
        config(['unimice.base_url' => null]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('UNIMICE_API_BASE_URL');

        new UniMiceClient();
    }
}
