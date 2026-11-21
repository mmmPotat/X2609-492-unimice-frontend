<?php

declare(strict_types=1);

namespace App\Services\UniMice\Endpoints;

use App\Services\UniMice\ApiResponse;
use App\Services\UniMice\Dto\CityDto;
use App\Services\UniMice\Dto\EventDto;
use App\Services\UniMice\Dto\EventStatusDto;
use App\Services\UniMice\Dto\EventTypeDto;
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
     * Одна заявка по id (GET /events/{id}). Ответ — тот же объект,
     * что элемент списка, плюс связанные сущности (они в DTO пока
     * не описаны и spatie игнорирует их).
     *
     * @throws UniMiceApiException
     */
    public function get(int $id): EventDto
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/events/'.$id),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        if (! is_array($response->data)) {
            throw new UniMiceApiException('Пустой ответ API при загрузке заявки', 0);
        }

        return EventDto::from($response->data);
    }

    /**
     * Создание заявки (POST /events). $data — тело SaveEventRequest,
     * обязательные поля собирает контроллер формы.
     *
     * @param array<string, mixed> $data
     * @throws UniMiceApiException
     */
    public function create(array $data): EventDto
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('POST', '/events', [], $data),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        $event = is_array($response->data) ? ($response->data['event'] ?? $response->data) : null;

        if (! is_array($event) || ! isset($event['id'])) {
            throw new UniMiceApiException('Пустой ответ API при создании заявки', 0);
        }

        return EventDto::from($event);
    }

    /**
     * Справочник типов мероприятий (для формы создания).
     *
     * @return DataCollection<int, EventTypeDto>
     * @throws UniMiceApiException
     */
    public function types(): DataCollection
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/events/types'),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        $items = is_array($response->data) ? ($response->data['items'] ?? []) : [];

        return EventTypeDto::collect($items, DataCollection::class);
    }

    /**
     * Один город по id (GET /cities/{id}). Нужен, чтобы показать
     * подпись выбранного города при повторном рендере формы
     * (например, после ошибки валидации).
     *
     * @throws UniMiceApiException
     */
    public function city(int $id): CityDto
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/cities/'.$id),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        if (! is_array($response->data)) {
            throw new UniMiceApiException('Пустой ответ API при загрузке города', 0);
        }

        return CityDto::from($response->data);
    }

    /**
     * Поиск городов (GET /cities/search?query=...). Без query API
     * отдаёт пустой список.
     *
     * @return DataCollection<int, CityDto>
     * @throws UniMiceApiException
     */
    public function searchCities(string $query): DataCollection
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/cities/search', ['query' => $query]),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        $items = is_array($response->data) ? $response->data : [];

        return CityDto::collect($items, DataCollection::class);
    }

    /**
     * Справочник статусов заявок — нужен для фильтров и подписей.
     *
     * @return DataCollection<int, EventStatusDto>
     * @throws UniMiceApiException
     */
    public function statuses(): DataCollection
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/events/statuses'),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        $items = is_array($response->data) ? ($response->data['items'] ?? []) : [];

        return EventStatusDto::collect($items, DataCollection::class);
    }

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
