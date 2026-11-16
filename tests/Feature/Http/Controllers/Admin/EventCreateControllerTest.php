<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

final class EventCreateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'unimice.base_url' => 'https://api.unimice.test',
            'unimice.auth.email' => 'bot@example.com',
            'unimice.auth.password' => 'secret',
        ]);
    }

    public function test_city_query_returns_options_instead_of_creating(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/cities/search*' => Http::response([
                'status' => 'success',
                'data' => [
                    ['id' => 6, 'name' => 'Москва', 'nameEn' => 'Moscow', 'country' => null, 'timezone' => null],
                    ['id' => 7, 'name' => 'Москва-2', 'nameEn' => null, 'country' => null, 'timezone' => null],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.event-create'), $this->validPayload(cityId: null, cityQuery: 'Москва'));

        $response->assertRedirect();

        // заявка НЕ создавалась — был только поиск городов
        Http::assertNotSent(static fn ($request) => $request->method() === 'POST'
            && str_contains((string) $request->url(), '/events'));

        Http::assertSent(static fn ($request) => str_contains((string) $request->url(), '/cities/search')
            && ($request->data()['query'] ?? null) === 'Москва');

        $response->assertSessionHas('cityOptions', [6 => 'Москва', 7 => 'Москва-2']);
    }

    public function test_empty_city_query_shows_validation_error(): void
    {
        Http::fake(['*/auth/login' => Http::response(['token' => 'jwt-abc'])]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.event-create'), $this->validPayload(cityId: null, cityQuery: null));

        $response->assertSessionHasErrors(['cityQuery']);
        Http::assertNothingSent();
    }

    public function test_create_event_posts_complete_body_and_redirects_to_detail(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users/me' => Http::response([
                'status' => 'success',
                'data' => ['user' => ['id' => 690, 'clientId' => 5, 'legalEntity' => ['id' => 5]]],
            ]),
            '*/events' => Http::response([
                'status' => 'success',
                'data' => ['event' => $this->fakeCreatedEvent(55)],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.event-create'), $this->validPayload());

        $response->assertRedirect('/admin/page/event-detail?id=55');
        $response->assertSessionHas('successMessage');

        Http::assertSent(static function ($request) {
            return $request->method() === 'POST'
                && str_contains((string) $request->url(), '/events')
                && ($request['title'] ?? null) === 'Тестовая конференция'
                && ($request['statusId'] ?? null) === 2
                && ($request['typeId'] ?? null) === 1
                && ($request['categoryId'] ?? null) === 3
                && ($request['cityId'] ?? null) === 6
                && ($request['clientInitiatorId'] ?? null) === 690
                && ($request['agencyLegalEntityId'] ?? null) === 5
                && ($request['participantsCount'] ?? null) === 50
                && ($request['startDate'] ?? null) === '2026-12-01 10:00:00'
                && ($request['finishDate'] ?? null) === '2026-12-01 18:00:00';
        });
    }

    public function test_fee_category_fixes_type_and_category(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users/me' => Http::response([
                'status' => 'success',
                'data' => ['user' => ['id' => 690, 'clientId' => 5, 'legalEntity' => ['id' => 5]]],
            ]),
            '*/events' => Http::response([
                'status' => 'success',
                'data' => ['event' => $this->fakeCreatedEvent(56)],
            ]),
        ]);

        $payload = $this->validPayload();
        unset($payload['typeId']); // у орг. взноса тип фиксирован — поле не обязательно

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.event-create', ['category' => 'fee']), $payload);

        $response->assertRedirect('/admin/page/event-detail?id=56');

        Http::assertSent(static fn ($request) => ($request['typeId'] ?? null) === 29
            && ($request['categoryId'] ?? null) === 6);
    }

    public function test_api_validation_errors_are_mapped_to_form_fields(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users/me' => Http::response([
                'status' => 'success',
                'data' => ['user' => ['id' => 690, 'clientId' => 5, 'legalEntity' => ['id' => 5]]],
            ]),
            '*/events' => Http::response([
                'status' => 'error',
                'data' => null,
                'message' => 'Validation failed',
                'errors' => ['title' => ['This value should not be blank.']],
            ], 422),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.event-create'), $this->validPayload());

        $response->assertSessionHasErrors(['title']);
    }

    public function test_api_failure_shows_alert_and_keeps_input(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users/me' => Http::response([
                'status' => 'success',
                'data' => ['user' => ['id' => 690, 'clientId' => 5, 'legalEntity' => ['id' => 5]]],
            ]),
            '*/events' => Http::response([
                'status' => 'error',
                'data' => null,
                'message' => 'Internal server error',
                'errors' => null,
            ], 500),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.event-create'), $this->validPayload());

        $response->assertRedirect();
        $response->assertSessionHas('apiError');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(?int $cityId = 6, ?string $cityQuery = null): array
    {
        return [
            'title' => 'Тестовая конференция',
            'typeId' => 1,
            'participantsCount' => 50,
            'startDate' => '2026-12-01T10:00',
            'finishDate' => '2026-12-01T18:00',
            'cityQuery' => $cityQuery,
            'cityId' => $cityId,
            'description' => 'Комментарий',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeCreatedEvent(int $id): array
    {
        return [
            'id' => $id,
            'title' => 'Тестовая конференция',
            'description' => '',
            'status' => ['id' => 2, 'name' => 'Новая заявка', 'nameEn' => 'New'],
            'type' => ['id' => 1, 'name' => 'Конференция', 'nameEn' => 'Conference'],
            'category' => [
                'id' => 3,
                'code' => ['name' => 'Event', 'value' => 'event'],
                'name' => 'Мероприятие',
                'nameEn' => 'Event',
                'arrangementFee' => false,
            ],
            'city' => ['id' => 6, 'name' => 'Москва', 'nameEn' => 'Moscow', 'country' => null, 'timezone' => null],
            'startDate' => '2026-12-01T10:00:00+03:00',
            'finishDate' => '2026-12-01T18:00:00+03:00',
            'startDateFormat' => '01-12-2026',
            'finishDateFormat' => '01-12-2026',
            'supplierName' => null,
            'venue' => null,
            'clientLegalEntity' => null,
            'agencyLegalEntity' => null,
            'participantsCount' => 50,
            'createdAt' => '2026-11-16T17:00:00+03:00',
            'canApprove' => false,
        ];
    }
}
