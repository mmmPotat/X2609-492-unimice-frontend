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

    public function test_city_id_is_required(): void
    {
        Http::fake(['*/auth/login' => Http::response(['token' => 'jwt-abc'])]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.event-create'), $this->validPayload(cityId: null));

        $response->assertSessionHasErrors(['cityId']);
        Http::assertNothingSent();
    }

    public function test_city_search_endpoint_returns_options(): void
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
            ->getJson(route('admin.event-create.cities', ['query' => 'Москва']));

        $response->assertOk();
        $response->assertJsonCount(2);
        $response->assertJsonPath('0.label', 'Москва');
        $response->assertJsonPath('0.value', '6');
        $response->assertJsonPath('1.label', 'Москва-2');
        $response->assertJsonPath('1.value', '7');

        Http::assertSent(static fn ($request) => str_contains((string) $request->url(), '/cities/search')
            && ($request->data()['query'] ?? null) === 'Москва');
    }

    public function test_city_search_endpoint_with_empty_query_returns_empty_list(): void
    {
        Http::fake(['*/auth/login' => Http::response(['token' => 'jwt-abc'])]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->getJson(route('admin.event-create.cities'));

        $response->assertOk();
        $response->assertExactJson([]);

        // пустой запрос в API не уходит — отвечаем сразу
        Http::assertNotSent(static fn ($request) => str_contains((string) $request->url(), '/cities/search'));
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
                && ($request['startDate'] ?? null) === '2026-12-01'
                && ($request['finishDate'] ?? null) === '2026-12-02';
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

    public function test_finish_date_cannot_be_earlier_than_start(): void
    {
        Http::fake(['*/auth/login' => Http::response(['token' => 'jwt-abc'])]);

        $payload = $this->validPayload();
        $payload['finishDate'] = '2026-11-30';

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.event-create'), $payload);

        $response->assertSessionHasErrors(['finishDate']);
        Http::assertNothingSent();
    }

    public function test_same_day_start_and_finish_is_allowed(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users/me' => Http::response([
                'status' => 'success',
                'data' => ['user' => ['id' => 690, 'clientId' => 5, 'legalEntity' => ['id' => 5]]],
            ]),
            '*/events' => Http::response([
                'status' => 'success',
                'data' => ['event' => $this->fakeCreatedEvent(57)],
            ]),
        ]);

        $payload = $this->validPayload();
        $payload['finishDate'] = $payload['startDate']; // однодневное мероприятие

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.event-create'), $payload);

        $response->assertRedirect('/admin/page/event-detail?id=57');
    }

    public function test_datetime_string_is_rejected(): void
    {
        Http::fake(['*/auth/login' => Http::response(['token' => 'jwt-abc'])]);

        $payload = $this->validPayload();
        $payload['startDate'] = '2026-12-01T10:00'; // старый формат с временем

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.event-create'), $payload);

        $response->assertSessionHasErrors(['startDate']);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(?int $cityId = 6): array
    {
        return [
            'title' => 'Тестовая конференция',
            'typeId' => 1,
            'participantsCount' => 50,
            'startDate' => '2026-12-01',
            'finishDate' => '2026-12-02',
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
