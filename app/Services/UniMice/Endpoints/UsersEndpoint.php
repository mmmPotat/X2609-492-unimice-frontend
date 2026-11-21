<?php

declare(strict_types=1);

namespace App\Services\UniMice\Endpoints;

use App\Services\UniMice\ApiResponse;
use App\Services\UniMice\Dto\GroupDto;
use App\Services\UniMice\Dto\MeDto;
use App\Services\UniMice\Dto\RoleDto;
use App\Services\UniMice\Dto\UserDto;
use App\Services\UniMice\Dto\UsersResultDto;
use App\Services\UniMice\UniMiceApiException;
use App\Services\UniMice\UniMiceClient;
use Spatie\LaravelData\DataCollection;

/**
 * Доступ к пользователям платформы.
 */
final class UsersEndpoint
{
    public function __construct(
        private readonly UniMiceClient $client,
    ) {}

    /**
     * Текущий пользователь (по токену сервисной учётки).
     *
     * @throws UniMiceApiException
     */
    public function me(): MeDto
    {
        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/users/me'),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        $user = is_array($response->data) ? ($response->data['user'] ?? null) : null;

        if (! is_array($user)) {
            throw new UniMiceApiException('Пустой ответ API при загрузке профиля', 0);
        }

        $legalEntity = is_array($user['legalEntity'] ?? null) ? $user['legalEntity'] : null;
        $client = is_array($user['client'] ?? null) ? $user['client'] : null;
        $chief = is_array($user['chief'] ?? null) ? $user['chief'] : null;

        return new MeDto(
            id: (int) ($user['id'] ?? 0),
            clientId: isset($user['clientId']) ? (int) $user['clientId'] : null,
            legalEntityId: isset($legalEntity['id']) ? (int) $legalEntity['id'] : null,
            email: isset($user['email']) ? (string) $user['email'] : null,
            fullName: isset($user['fullName']) ? (string) $user['fullName'] : null,
            shortName: isset($user['shortName']) ? (string) $user['shortName'] : null,
            post: isset($user['post']) ? (string) $user['post'] : null,
            phone: isset($user['phone']) ? (string) $user['phone'] : null,
            createdAt: isset($user['createdAt']) ? (string) $user['createdAt'] : null,
            role: is_array($user['role'] ?? null) ? RoleDto::from($user['role']) : null,
            group: is_array($user['group'] ?? null) ? GroupDto::from($user['group']) : null,
            clientName: isset($client['name']) ? (string) $client['name'] : null,
            chiefName: isset($chief['fullName']) ? (string) $chief['fullName'] : null,
        );
    }

    /**
     * Список сотрудников (GET /users, data.items + data.total).
     *
     * Документированные фильтры: query (поиск по ФИО/email),
     * clientId, legalEntityId, page, limit.
     *
     * @param array{query?: string, clientId?: int, legalEntityId?: int, page?: int, limit?: int} $filters
     * @throws UniMiceApiException
     */
    public function list(array $filters = []): UsersResultDto
    {
        $query = array_filter([
            'query' => $filters['query'] ?? null,
            'clientId' => $filters['clientId'] ?? null,
            'legalEntityId' => $filters['legalEntityId'] ?? null,
            'page' => $filters['page'] ?? null,
            'limit' => $filters['limit'] ?? null,
        ], static fn ($value): bool => $value !== null && $value !== '');

        $response = ApiResponse::fromHttp(
            $this->client->request('GET', '/users', $query),
        );

        if (($exception = $response->toException()) !== null) {
            throw $exception;
        }

        $items = is_array($response->data) ? ($response->data['items'] ?? []) : [];
        $total = is_array($response->data) ? (int) ($response->data['total'] ?? count($items)) : 0;

        return new UsersResultDto(
            items: UserDto::collect($this->withChiefNames($items), DataCollection::class),
            total: $total,
        );
    }

    /**
     * Вложенный chief приходит полным объектом пользователя —
     * для таблицы достаточно ФИО, вытаскиваем его до сборки DTO.
     *
     * @param array<array-key, mixed> $items
     * @return array<array-key, mixed>
     */
    private function withChiefNames(array $items): array
    {
        foreach ($items as $i => $item) {
            if (is_array($item) && is_array($item['chief'] ?? null)) {
                $items[$i]['chiefName'] = isset($item['chief']['fullName'])
                    ? (string) $item['chief']['fullName']
                    : null;
                unset($items[$i]['chief']);
            }
        }

        return $items;
    }
}
