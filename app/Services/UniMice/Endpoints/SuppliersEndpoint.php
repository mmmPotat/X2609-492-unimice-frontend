<?php

declare(strict_types=1);

namespace App\Services\UniMice\Endpoints;

use App\Services\UniMice\ApiResponse;
use App\Services\UniMice\Dto\SupplierDto;
use App\Services\UniMice\Dto\SupplierTypeDto;
use App\Services\UniMice\Dto\SuppliersResultDto;
use App\Services\UniMice\UniMiceApiException;
use App\Services\UniMice\UniMiceClient;
use Spatie\LaravelData\DataCollection;

/**
 * Доступ к поставщикам платформы. Для каталога площадок
 * используем типы 1–14 (отели, рестораны, лофты и т.п.) —
 * «Билеты» и «Орг. комитет» площадками не являются.
 */
final class SuppliersEndpoint
{
    /** Типы, считающиеся площадками в каталоге. */
    public const VENUE_TYPE_IDS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14];

    public function __construct(
        private readonly UniMiceClient $client,
    ) {}

    /**
     * Список поставщиков с фильтрами (GET /suppliers).
     *
     * Форматы фильтров проверены на живом API:
     *  - typeIds — строкой "[1,2,3]" (JSON-массив, так требует валидация);
     *  - stars   — строкой "4,5" (plain CSV, JSON-массив отвергается с 400);
     *  - isFavorite — строкой "true"/"false";
     *  - price/size/capacity Min и Max — числа, фильтруют по помещениям.
     *
     * @param array{
     *     typeIds?: list<int>, name?: string, address?: string, cityId?: int,
     *     stars?: list<int>, isFavorite?: bool,
     *     priceMin?: float, priceMax?: float, sizeMin?: int, sizeMax?: int,
     *     capacityMin?: int, capacityMax?: int, page?: int, limit?: int,
     * } $filters
     * @throws UniMiceApiException
     */
    public function list(array $filters = []): SuppliersResultDto
    {
        $query = array_filter([
            'name' => $filters['name'] ?? null,
            'address' => $filters['address'] ?? null,
            'cityId' => $filters['cityId'] ?? null,
            'priceMin' => $filters['priceMin'] ?? null,
            'priceMax' => $filters['priceMax'] ?? null,
            'sizeMin' => $filters['sizeMin'] ?? null,
            'sizeMax' => $filters['sizeMax'] ?? null,
            'capacityMin' => $filters['capacityMin'] ?? null,
            'capacityMax' => $filters['capacityMax'] ?? null,
            'page' => $filters['page'] ?? null,
            'limit' => $filters['limit'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== '');

        if (isset($filters['typeIds']) && $filters['typeIds'] !== []) {
            $query['typeIds'] = '['.implode(',', $filters['typeIds']).']';
        }

        if (isset($filters['stars']) && $filters['stars'] !== []) {
            $query['stars'] = implode(',', $filters['stars']);
        }

        if (isset($filters['isFavorite'])) {
            $query['isFavorite'] = $filters['isFavorite'] ? 'true' : 'false';
        }

        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/suppliers', $query),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        $items = is_array($response->data) ? ($response->data['items'] ?? []) : [];
        $total = is_array($response->data) ? (int) ($response->data['total'] ?? count($items)) : 0;

        return new SuppliersResultDto(
            items: SupplierDto::collect($items, DataCollection::class),
            total: $total,
        );
    }

    /**
     * Справочник типов поставщиков (GET /suppliers/types).
     *
     * @return DataCollection<int, SupplierTypeDto>
     * @throws UniMiceApiException
     */
    public function types(): DataCollection
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/suppliers/types'),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        $items = is_array($response->data) ? ($response->data['items'] ?? []) : [];

        return SupplierTypeDto::collect($items, DataCollection::class);
    }
}
