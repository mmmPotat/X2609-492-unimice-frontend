<?php

declare(strict_types=1);

namespace App\Services\UniMice\Endpoints;

use App\Services\UniMice\ApiResponse;
use App\Services\UniMice\Dto\ClientDto;
use App\Services\UniMice\Dto\ClientLegalEntityDto;
use App\Services\UniMice\UniMiceApiException;
use App\Services\UniMice\UniMiceClient;
use Spatie\LaravelData\DataCollection;

/**
 * Доступ к клиентам платформы (в нашем контексте — к данным
 * агентства: карточка «моей» компании и её юрлица).
 */
final class ClientsEndpoint
{
    public function __construct(
        private readonly UniMiceClient $client,
    ) {}

    /**
     * Клиент текущего пользователя (GET /clients отдаёт один объект,
     * без items/total).
     *
     * @throws UniMiceApiException
     */
    public function current(): ClientDto
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/clients'),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        if (! is_array($response->data)) {
            throw new UniMiceApiException('Пустой ответ API при загрузке компании', 0);
        }

        return ClientDto::from($response->data);
    }

    /**
     * Юрлица клиента (GET /client/{clientId}/legal-entities/).
     * Адрес и город лежат во вложенном supplier — поднимаем наверх.
     *
     * @return DataCollection<int, ClientLegalEntityDto>
     * @throws UniMiceApiException
     */
    public function legalEntities(int $clientId): DataCollection
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/client/'.$clientId.'/legal-entities/'),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        $items = is_array($response->data) ? ($response->data['items'] ?? []) : [];

        foreach ($items as $i => $item) {
            if (! is_array($item)) {
                continue;
            }

            $supplier = is_array($item['supplier'] ?? null) ? $item['supplier'] : [];

            $items[$i]['address'] = isset($supplier['address']) ? (string) $supplier['address'] : null;
            $items[$i]['city'] = $this->extractCity($supplier);
            unset($items[$i]['supplier'], $items[$i]['extraFields']);
        }

        return ClientLegalEntityDto::collect($items, DataCollection::class);
    }

    /**
     * @param array<string, mixed> $supplier
     * @return array<string, mixed>|null
     */
    private function extractCity(array $supplier): ?array
    {
        $city = $supplier['city'] ?? null;

        if (! is_array($city) || ! isset($city['id'], $city['name'])) {
            return null;
        }

        return [
            'id' => (int) $city['id'],
            'name' => (string) $city['name'],
            'nameEn' => isset($city['nameEn']) ? (string) $city['nameEn'] : null,
            'country' => is_array($city['country'] ?? null) ? $city['country'] : null,
            'timezone' => isset($city['timezone']) ? (string) $city['timezone'] : null,
        ];
    }
}
