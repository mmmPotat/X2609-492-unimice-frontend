<?php

declare(strict_types=1);

namespace App\Services\UniMice\Endpoints;

use App\Services\UniMice\ApiResponse;
use App\Services\UniMice\Dto\MeDto;
use App\Services\UniMice\UniMiceApiException;
use App\Services\UniMice\UniMiceClient;

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

        return new MeDto(
            id: (int) ($user['id'] ?? 0),
            clientId: isset($user['clientId']) ? (int) $user['clientId'] : null,
            legalEntityId: isset($legalEntity['id']) ? (int) $legalEntity['id'] : null,
        );
    }
}
