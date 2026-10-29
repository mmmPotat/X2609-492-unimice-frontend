<?php

declare(strict_types=1);

namespace Tests\Feature\Services\UniMice;

use App\Services\UniMice\ApiResponse;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ApiResponseTest extends TestCase
{
    public function test_success_envelope_is_parsed(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'success',
                'data' => ['events' => [['id' => 1], ['id' => 2]]],
                'message' => null,
                'errors' => null,
                'timestamp' => '2026-10-29 19:30:31',
            ], 200),
        ]);

        $api = ApiResponse::fromHttp(Http::get('https://api.test/events'));

        $this->assertTrue($api->isSuccess());
        $this->assertFalse($api->isError());
        $this->assertSame(200, $api->httpStatus);
        $this->assertSame('2026-10-29 19:30:31', $api->timestamp);
        $this->assertCount(2, $api->data['events']);
        $this->assertNull($api->toException());
    }

    public function test_422_errors_are_mapped_by_field(): void
    {
        Http::fake([
            '*' => Http::response([
                'status' => 'error',
                'data' => null,
                'message' => 'Некорректно заполнены поля: title',
                'errors' => [
                    'title' => ['Значение не должно быть пустым.'],
                    'statusId' => ['Значение должно быть положительным.'],
                ],
                'timestamp' => '2026-10-29 19:31:00',
            ], 422),
        ]);

        $api = ApiResponse::fromHttp(Http::post('https://api.test/events', []));
        $exception = $api->toException();

        $this->assertTrue($api->isError());
        $this->assertNotNull($exception);
        $this->assertSame(422, $exception->httpStatus);
        $this->assertSame('Некорректно заполнены поля: title', $exception->getMessage());
        $this->assertSame(['title', 'statusId'], array_keys($exception->errors ?? []));
        $this->assertSame(['Значение не должно быть пустым.'], $exception->errors['title']);
        $this->assertContains('title — Значение не должно быть пустым.', $exception->flattenedErrors());
    }

    public function test_http_error_without_envelope_still_becomes_exception(): void
    {
        Http::fake(['*' => Http::response('Service Unavailable', 503)]);

        $exception = ApiResponse::fromHttp(Http::get('https://api.test/events'))->toException();

        $this->assertNotNull($exception);
        $this->assertSame(503, $exception->httpStatus);
        $this->assertNull($exception->errors);
    }
}
