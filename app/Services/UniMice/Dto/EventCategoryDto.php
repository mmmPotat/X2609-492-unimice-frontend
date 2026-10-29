<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/** Категория заявки. */
final class EventCategoryDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly ?CategoryCodeDto $code,
        public readonly string $name,
        public readonly ?string $nameEn,
        public readonly bool $arrangementFee,
    ) {}
}
