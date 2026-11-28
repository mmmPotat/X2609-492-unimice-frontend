<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/**
 * Пользователь внутри ответа заявки (инициатор, менеджер, наблюдатели).
 *
 * Полный UserDto сюда не подходит: вложенный объект содержит лишь часть
 * полей (например, нет fullName), а не-nullable поля UserDto на таком
 * ответе упали бы. Всё необязательное, кроме id.
 */
final class EventUserDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $email = null,
        public readonly ?string $firstName = null,
        public readonly ?string $lastName = null,
        public readonly ?string $middleName = null,
        public readonly ?string $shortName = null,
        public readonly ?string $post = null,
        public readonly ?string $phone = null,
        public readonly ?RoleDto $role = null,
        public readonly ?ClientDto $client = null,
    ) {}

    public function fullName(): string
    {
        $parts = array_filter([$this->lastName, $this->firstName, $this->middleName]);

        return $parts === [] ? ($this->shortName ?? '—') : implode(' ', $parts);
    }
}
