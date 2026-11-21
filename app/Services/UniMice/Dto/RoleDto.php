<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/** Справочник: роль пользователя на платформе. */
final class RoleDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $nameEn,
    ) {}
}
