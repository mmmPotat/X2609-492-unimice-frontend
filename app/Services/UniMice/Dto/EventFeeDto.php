<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/** Организационный взнос заявки (элемент eventFees ответа GET /events/{id}). */
final class EventFeeDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly float $amount,
        public readonly ?string $vatRate = null,
        public readonly ?string $description = null,
        public readonly ?CarbonImmutable $createdAt = null,
    ) {}
}
