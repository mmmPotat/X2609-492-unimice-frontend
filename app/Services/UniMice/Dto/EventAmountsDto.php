<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/** Итоговые суммы заявки (amountSummaries ответа GET /events/{id}). */
final class EventAmountsDto extends Data
{
    public function __construct(
        public readonly ?MoneyDto $totalAmountServices = null,
        public readonly ?MoneyDto $totalAmountFees = null,
        public readonly ?MoneyDto $totalAmount = null,
    ) {}
}
