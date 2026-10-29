<?php

declare(strict_types=1);

namespace App\MoonShine\Support;

use MoonShine\Support\Enums\Color;

/**
 * Цвет бейджа статуса заявки по id из справочника API
 * (GET /api/events/statuses).
 */
final class EventStatusColor
{
    public static function byId(int $statusId): Color
    {
        return match ($statusId) {
            1 => Color::GRAY,      // Черновик
            2 => Color::INFO,      // Новая заявка
            3, 4 => Color::PRIMARY, // В работе у менеджера / у площадки
            5 => Color::WARNING,   // На согласовании
            6 => Color::SUCCESS,   // Подтверждена
            7 => Color::SECONDARY, // Мероприятие прошло
            8 => Color::WARNING,   // Внесение дополнительных затрат
            9 => Color::SUCCESS,   // Закрыта
            10 => Color::ERROR,    // Отменена
            default => Color::SECONDARY,
        };
    }
}
