<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/**
 * Дополнительное поле заявки (элемент extraFields ответа GET /events/{id}).
 *
 * Значение может быть как в value, так и во вложенном
 * eventExtraFieldValue — смотрим оба.
 */
final class EventExtraFieldDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $type = null,
        public readonly bool $required = false,
        public readonly array $options = [],
        public readonly mixed $value = null,
        public readonly mixed $eventExtraFieldValue = null,
    ) {}

    public function displayValue(): string
    {
        $raw = $this->value
            ?? (is_array($this->eventExtraFieldValue) ? ($this->eventExtraFieldValue['value'] ?? null) : $this->eventExtraFieldValue);

        if (is_bool($raw)) {
            return $raw ? 'Да' : 'Нет';
        }

        if (is_array($raw)) {
            return implode(', ', array_map(static fn ($item): string => is_scalar($item) ? (string) $item : '', $raw)) ?: '—';
        }

        return $raw !== null && $raw !== '' ? (string) $raw : '—';
    }
}
