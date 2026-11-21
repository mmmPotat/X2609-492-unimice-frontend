<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/**
 * Пользователь платформы (сотрудник агентства).
 * Элемент коллекции GET /users (data.items[]).
 *
 * chief вложен тем же набором полей — рекурсию на уровне DTO
 * не разворачиваем, из него берём только ФИО (chiefName).
 */
final class UserDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $middleName,
        public readonly string $fullName,
        public readonly ?string $shortName,
        public readonly ?string $post,
        public readonly ?string $phone,
        public readonly ?string $createdAt,
        public readonly ?RoleDto $role,
        public readonly ?GroupDto $group,
        public readonly ?ClientDto $client,
        public readonly ?string $chiefName = null,
    ) {}
}
