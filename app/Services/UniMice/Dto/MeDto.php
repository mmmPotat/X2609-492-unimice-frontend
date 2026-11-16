<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/**
 * Текущий пользователь API (GET /users/me, data.user).
 *
 * Нужен при создании заявок: бэкенд требует clientInitiatorId
 * (= id пользователя, не клиента) и agencyLegalEntityId.
 */
final class MeDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $clientId,
        public readonly ?int $legalEntityId,
    ) {}
}
