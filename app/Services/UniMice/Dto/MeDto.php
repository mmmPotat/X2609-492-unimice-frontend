<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/**
 * Текущий пользователь API (GET /users/me, data.user).
 *
 * Используется в двух местах: контроллер создания заявок читает
 * id/clientId/legalEntityId (бэкенд требует их при POST /events),
 * экран «Профиль» — остальные поля.
 */
final class MeDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $clientId,
        public readonly ?int $legalEntityId,
        public readonly ?string $email = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $middleName = null,
        public readonly ?string $fullName = null,
        public readonly ?string $shortName = null,
        public readonly ?string $post = null,
        public readonly ?string $phone = null,
        public readonly ?string $createdAt = null,
        public readonly ?RoleDto $role = null,
        public readonly ?GroupDto $group = null,
        public readonly ?string $clientName = null,
        public readonly ?string $chiefName = null,
    ) {}
}
