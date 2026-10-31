<?php

declare(strict_types=1);

namespace Tests\Feature\MoonShine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

final class EventsPageTest extends TestCase
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

    public function test_page_renders_events_and_status_filter_chips(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            // порядок важен: специфичный паттерн до общего
            '*/events/statuses' => Http::response([
                'status' => 'success',
                'data' => ['items' => [
                    ['id' => 1, 'name' => 'Черновик', 'nameEn' => 'Draft'],
                    ['id' => 2, 'name' => 'Новая заявка', 'nameEn' => 'New'],
                ]],
            ]),
            '*/events*' => Http::response([
                'status' => 'success',
                'data' => ['events' => [$this->fakeEvent()]],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/events-page');

        $response->assertOk();
        $response->assertSee('Новая заявка'); // чип фильтра
        $response->assertSee('Конференция в Москве'); // строка таблицы
    }

    public function test_status_query_parameter_is_passed_to_api(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/events/statuses' => Http::response([
                'status' => 'success',
                'data' => ['items' => [
                    ['id' => 1, 'name' => 'Черновик', 'nameEn' => 'Draft'],
                    ['id' => 2, 'name' => 'Новая заявка', 'nameEn' => 'New'],
                ]],
            ]),
            '*/events*' => Http::response([
                'status' => 'success',
                'data' => ['events' => []],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/events-page?status[]=2&status[]=6');

        $response->assertOk();

        Http::assertSent(static function ($request) {
            return ($request->data()['statusIds'] ?? null) === '[2,6]';
        });
    }

    public function test_page_works_without_filters(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/events/statuses' => Http::response([
                'status' => 'success',
                'data' => ['items' => [
                    ['id' => 1, 'name' => 'Черновик', 'nameEn' => 'Draft'],
                ]],
            ]),
            '*/events*' => Http::response([
                'status' => 'success',
                'data' => ['events' => []],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/events-page');

        $response->assertOk();

        Http::assertSent(static function ($request) {
            return ($request->url() === 'https://api.unimice.test/events')
                && ! isset($request->data()['statusIds']);
        });
    }

    public function test_page_renders_twice_with_serialized_cache(): void
    {
        // Регрессия: DataCollection spatie-data нельзя класть в кеш как
        // объект (сериализация ломается), поэтому чипы строятся из
        // plain-массива. Только сериализующий store (database) ловит баг.
        config(['cache.default' => 'database']);

        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/events/statuses' => Http::response([
                'status' => 'success',
                'data' => ['items' => [
                    ['id' => 1, 'name' => 'Черновик', 'nameEn' => 'Draft'],
                ]],
            ]),
            '*/events*' => Http::response([
                'status' => 'success',
                'data' => ['events' => []],
            ]),
        ]);

        $user = MoonshineUser::factory()->create();

        // второй запрос читает справочник из кеша — там баг и проявлялся
        $this->actingAs($user, 'moonshine')->get('/admin/page/events-page')->assertOk();
        $this->actingAs($user, 'moonshine')->get('/admin/page/events-page')->assertOk();
    }

    public function test_list_rows_have_open_buttons(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/events/statuses' => Http::response([
                'status' => 'success',
                'data' => ['items' => [
                    ['id' => 2, 'name' => 'Новая заявка', 'nameEn' => 'New'],
                ]],
            ]),
            '*/events*' => Http::response([
                'status' => 'success',
                'data' => ['events' => [$this->fakeEvent()]],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/events-page');

        $response->assertOk();
        // ссылка на карточку заявки (id из fakeEvent)
        $response->assertSee('id=1', escape: false);
        $response->assertSee('Открыть');
    }

    public function test_detail_page_renders_event_card(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/events/1' => Http::response([
                'status' => 'success',
                'data' => $this->fakeEvent(),
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/event-detail?id=1');

        $response->assertOk();
        $response->assertSee('Конференция в Москве');
        $response->assertSee('Параметры');
        $response->assertSee('Новая заявка');
        $response->assertSee('Москва');
    }

    public function test_detail_page_404_for_missing_event(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/events/999' => Http::response([
                'status' => 'error',
                'data' => null,
                'message' => 'Event not found',
                'errors' => null,
                'timestamp' => '2026-10-31 18:00:00',
            ], 404),
        ]);

        $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/event-detail?id=999')
            ->assertNotFound();
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeEvent(): array
    {
        return [
            'id' => 1,
            'title' => 'Конференция в Москве',
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
            'city' => [
                'id' => 6,
                'name' => 'Москва',
                'nameEn' => 'Moscow',
                'country' => ['id' => 1, 'name' => 'Россия', 'nameEn' => 'Russia'],
                'timezone' => 'Europe/Moscow',
            ],
            'startDate' => '2026-11-01T10:00:00+03:00',
            'finishDate' => '2026-11-01T18:00:00+03:00',
            'startDateFormat' => '01-11-2026',
            'finishDateFormat' => '01-11-2026',
            'supplierName' => null,
            'venue' => 'Конференц-зал',
            'clientLegalEntity' => null,
            'agencyLegalEntity' => null,
            'participantsCount' => 10,
            'createdAt' => '2026-10-29T18:26:25+03:00',
            'canApprove' => false,
        ];
    }
}
