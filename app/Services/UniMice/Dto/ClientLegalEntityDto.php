<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/**
 * Юрлицо клиента из GET /client/{id}/legal-entities/ — для экрана
 * «О компании». Отличается от LegalEntityDto (который живёт внутри
 * заявки): адрес и город лежат во вложенном supplier.
 */
final class ClientLegalEntityDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $nameEn,
        public readonly ?string $inn,
        public readonly ?string $kpp,
        public readonly ?string $vatRate,
        public readonly ?string $address,
        public readonly ?CityDto $city,
        public readonly ?string $createdAt,
    ) {}
}
