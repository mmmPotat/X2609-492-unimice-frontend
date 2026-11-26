<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/** Страница списка поставщиков: GET /suppliers (data.items + data.total). */
final class SuppliersResultDto extends Data
{
    public function __construct(
        /** @var DataCollection<int, SupplierDto> */
        public readonly DataCollection $items,
        public readonly int $total,
    ) {}
}
