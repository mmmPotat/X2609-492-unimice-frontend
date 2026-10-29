<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/** Enum-подобный код категории: {name, value}. */
final class CategoryCodeDto extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    ) {}
}
