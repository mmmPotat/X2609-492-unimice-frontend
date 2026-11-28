<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Carbon\CarbonImmutable;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

/**
 * Заявка на мероприятие (событие) из API UniMICE.
 *
 * Соответствует элементу коллекции GET /events (data.events[]) и объекту
 * GET /events/{id}. Вложенные разделы карточки (инициатор, менеджер,
 * взносы, доп. поля, суммы) в коллекции обычно отсутствуют — поэтому
 * nullable. Поля API, не описанные здесь, spatie игнорирует.
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
        public readonly ?EventUserDto $clientInitiator = null,
        public readonly ?EventUserDto $agencyManager = null,
        public readonly ?EventUserDto $clientObserver = null,
        public readonly ?EventUserDto $agencyObserver = null,
        public readonly ?EventAmountsDto $amountSummaries = null,
        /** @var null|DataCollection<int, EventFeeDto> */
        #[DataCollectionOf(EventFeeDto::class)]
        public readonly ?DataCollection $eventFees = null,
        /** @var null|DataCollection<int, EventExtraFieldDto> */
        #[DataCollectionOf(EventExtraFieldDto::class)]
        public readonly ?DataCollection $extraFields = null,
        /** @var array<int, array<string, mixed>> Услуги; форма элемента в доке не зафиксирована, поэтому массив. */
        public readonly array $eventAmenities = [],
    ) {}
}
