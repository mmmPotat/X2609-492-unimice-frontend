<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/** Страница списка пользователей: GET /users (data.items + data.total). */
final class UsersResultDto extends Data
{
    public function __construct(
        /** @var DataCollection<int, UserDto> */
        public readonly DataCollection $items,
        public readonly int $total,
    ) {}
}
