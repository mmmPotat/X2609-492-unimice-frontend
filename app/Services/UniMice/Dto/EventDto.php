<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Data;

/**
 * Заявка на мероприятие (событие) из API UniMICE.
 *
 * Соответствует элементу коллекции GET /events (data.events[]).
 * Поля, не нужные текущему спринту (extraFields, eventFees,
 * amountSummaries и т.п.), намеренно не описаны — spatie их игнорирует.
 */
final class EventDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly ?string $description,
        public readonly EventStatusDto $status,
        public readonly EventTypeDto $type,
        public readonly EventCategoryDto $category,
        public readonly ?CityDto $city,
        public readonly ?CarbonImmutable $startDate,
        public readonly ?CarbonImmutable $finishDate,
        public readonly ?string $startDateFormat,
        public readonly ?string $finishDateFormat,
        public readonly ?string $supplierName,
        public readonly ?string $venue,
        public readonly ?LegalEntityDto $clientLegalEntity,
        public readonly ?LegalEntityDto $agencyLegalEntity,
        public readonly int $participantsCount,
        public readonly ?CarbonImmutable $createdAt,
        public readonly bool $canApprove,
    ) {}
}
