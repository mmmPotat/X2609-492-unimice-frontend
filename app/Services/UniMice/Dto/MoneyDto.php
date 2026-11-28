<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/** Сумма с разбивкой по НДС (блок amountSummaries ответа GET /events/{id}). */
final class MoneyDto extends Data
{
    public function __construct(
        public readonly float $withVat = 0,
        public readonly float $withoutVat = 0,
        public readonly float $vat = 0,
    ) {}

    public function format(float $value): string
    {
        return number_format($value, 2, ',', ' ').' ₽';
    }
}
