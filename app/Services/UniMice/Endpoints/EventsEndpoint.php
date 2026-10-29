<?php

declare(strict_types=1);

namespace App\Services\UniMice\Endpoints;

use App\Services\UniMice\ApiResponse;
use App\Services\UniMice\Dto\EventDto;
use App\Services\UniMice\UniMiceApiException;
use App\Services\UniMice\UniMiceClient;
use Spatie\LaravelData\DataCollection;

/**
 * Доступ к заявкам на мероприятия (событиям).
 *
 * API отдаёт весь набор заявок одним ответом (server-side пагинации
 * нет — параметры page/perPage в GET /events не документированы и не
 * поддерживаются), поэтому «список» здесь — отфильтрованная коллекция.
 */
final class EventsEndpoint
{
    /**
     * Документированные параметры фильтрации GET /events.
     * Значения-int собираются в строки через запятую — так их ждёт API.
     */
    private const FILTER_PARAMS = [
        'startDate',
        'finishDate',
        'clientInitiatorIds',
        'clientObserverIds',
        'statusIds',
        'typeIds',
        'categoryIds',
        'cityIds',
        'clientIds',
        'clientLegalEntityIds',
        'agencyManagerIds',
        'agencyObserverId',
    ];

    public function __construct(
        private readonly UniMiceClient $client,
    ) {}

    /**
     * Список заявок с фильтрами.
     *
     * Пример: $events->list(['statusIds' => [1, 2], 'cityIds' => 6])
     *
     * @param array<string, int|list<int>|string|null> $filters
     * @return DataCollection<int, EventDto>
     * @throws UniMiceApiException
     */
    public function list(array $filters = []): DataCollection
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/events', $this->buildQuery($filters)),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        $events = is_array($response->data) ? ($response->data['events'] ?? []) : [];

        return EventDto::collect($events, DataCollection::class);
    }

    /**
     * Параметры-даты передаются строкой, параметры-id — JSON-массивом
     * в формате "[1,2,3]" (так требует валидация API).
     */
    private const DATE_PARAMS = ['startDate', 'finishDate'];

    /**
     * @param array<string, int|list<int>|string|null> $filters
     * @return array<string, string>
     */
    private function buildQuery(array $filters): array
    {
        $query = [];

        foreach ($filters as $name => $value) {
            if (! in_array($name, self::FILTER_PARAMS, true) || $value === null || $value === '') {
                continue;
            }

            if (in_array($name, self::DATE_PARAMS, true)) {
                $query[$name] = (string) $value;

                continue;
            }

            $values = is_array($value) ? $value : [$value];

            $query[$name] = '['.implode(',', array_map('strval', $values)).']';
        }

        return $query;
    }
}
