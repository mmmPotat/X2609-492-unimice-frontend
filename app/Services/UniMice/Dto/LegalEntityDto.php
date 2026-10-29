<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/** Юридическое лицо (клиента или агентства). */
final class LegalEntityDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $nameEn,
        public readonly ?int $clientId,
        public readonly ?string $inn,
        public readonly ?string $kpp,
        public readonly ?string $address,
        public readonly ?string $vatRate,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}
}
