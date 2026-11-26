<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/**
 * Поставщик платформы. В каталоге площадок — это площадка
 * (отель, ресторан, лофт и т.п.).
 */
final class SupplierDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $nameEn,
        public readonly ?string $description,
        public readonly ?CityDto $city,
        public readonly ?string $address,
        public readonly ?SupplierTypeDto $type,
        public readonly ?SupplierStatusDto $status,
        public readonly ?int $stars,
        public readonly bool $isFavorite,
    ) {}
}
