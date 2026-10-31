<?php

declare(strict_types=1);

namespace Tests\Feature\Services\UniMice;

use App\Services\UniMice\Dto\EventDto;
use App\Services\UniMice\Endpoints\EventsEndpoint;
use App\Services\UniMice\UniMiceApiException;
use App\Services\UniMice\UniMiceClient;
use Illuminate\Support\Facades\Http;
use Spatie\LaravelData\DataCollection;
use Tests\TestCase;

final class EventsEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'unimice.base_url' => 'https://api.unimice.test',
            'unimice.auth.email' => 'bot@example.com',
            'unimice.auth.password' => 'secret',
        ]);
    }

    public function test_list_maps_events_to_dto_collection(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/events' => Http::response([
                'status' => 'success',
                'data' => ['events' => [
                    $this->fakeEvent(1, 'Конференция в Москве'),
                    $this->fakeEvent(2, 'Семинар'),
                ]],
                'message' => null,
                'errors' => null,
                'timestamp' => '2026-10-29 19:40:00',
            ]),
        ]);

        $events = (new EventsEndpoint(new UniMiceClient()))->list();

        $this->assertInstanceOf(DataCollection::class, $events);
        $this->assertCount(2, $events);
        $this->assertContainsOnlyInstancesOf(EventDto::class, $events->items());

        $first = $events->first();
        $this->assertSame(1, $first->id);
        $this->assertSame('Конференция в Москве', $first->title);
        $this->assertSame('Черновик', $first->status->name);
        $this->assertSame('Конференция', $first->type->name);
        $this->assertSame('Москва', $first->city->name);
        $this->assertSame(10, $first->participantsCount);
        $this->assertSame('01.11.2026', $first->startDate->format('d.m.Y'));
    }

    public function test_filters_are_sent_in_api_format_and_unknown_dropped(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            // query-строка входит в URL при сопоставлении паттернов — без '*' в конце
            // паттерн не сматчится, и запрос уйдёт в реальную сеть
            '*/events*' => Http::response(['status' => 'success', 'data' => ['events' => []]]),
        ]);

        (new EventsEndpoint(new UniMiceClient()))->list([
            'statusIds' => [1, 2],
            'startDate' => '2026-10-01 00:00:00',
            'unknownParam' => 'x',
            'hack' => '1; DROP',
        ]);

        Http::assertSent(static function ($request) {
            $query = $request->data();

            return ($query['statusIds'] ?? null) === '[1,2]'
                && ($query['startDate'] ?? null) === '2026-10-01 00:00:00'
                && ! isset($query['unknownParam'], $query['hack']);
        });
    }

    public function test_statuses_maps_items_to_dto_collection(): void
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
        ]);

        $statuses = (new EventsEndpoint(new UniMiceClient()))->statuses();

        $this->assertInstanceOf(DataCollection::class, $statuses);
        $this->assertCount(2, $statuses);

        $first = $statuses->first();
        $this->assertSame(1, $first->id);
        $this->assertSame('Черновик', $first->name);
        $this->assertSame('Draft', $first->nameEn);
    }

    public function test_get_maps_single_event(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/events/1' => Http::response([
                'status' => 'success',
                'data' => $this->fakeEvent(1, 'Конференция в Москве'),
                'message' => null,
                'errors' => null,
                'timestamp' => '2026-10-31 18:00:00',
            ]),
        ]);

        $event = (new EventsEndpoint(new UniMiceClient()))->get(1);

        $this->assertSame(1, $event->id);
        $this->assertSame('Конференция в Москве', $event->title);
        $this->assertSame('Черновик', $event->status->name);
    }

    public function test_get_404_becomes_typed_exception(): void
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

        try {
            (new EventsEndpoint(new UniMiceClient()))->get(999);
            $this->fail('Exception expected');
        } catch (UniMiceApiException $exception) {
            $this->assertSame(404, $exception->httpStatus);
        }
    }

    public function test_api_error_becomes_typed_exception(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/events' => Http::response([
                'status' => 'error',
                'data' => null,
                'message' => 'You are not allowed to perform this action',
                'errors' => null,
                'timestamp' => '2026-10-29 19:41:00',
            ], 403),
        ]);

        $this->expectException(UniMiceApiException::class);
        $this->expectExceptionMessage('You are not allowed to perform this action');

        (new EventsEndpoint(new UniMiceClient()))->list();
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeEvent(int $id, string $title): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'description' => '',
            'status' => ['id' => 1, 'name' => 'Черновик', 'nameEn' => 'Draft'],
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
