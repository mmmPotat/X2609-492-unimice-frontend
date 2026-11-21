<?php

declare(strict_types=1);

namespace App\MoonShine\Fields;

use MoonShine\UI\Fields\Date;

/**
 * Date, не падающий на мусорном вводе.
 *
 * Вендорный Date делает date($format, strtotime($value)): если строку не
 * разбирает strtotime (пользователь ввёл «12.12.121212», старое значение
 * подставляется полем автоматически при перерендере после ошибки
 * валидации), получаем TypeError и 500 вместо страницы с ошибкой.
 * Здесь такое значение просто отбрасывается — ошибку под полем всё равно
 * покажет валидация.
 */
final class SafeDate extends Date
{
    protected function resolveValue(): mixed
    {
        $value = $this->toValue();

        if (is_string($value) && strtotime($value) === false) {
            return $this->isNullable() ? null : '';
        }

        return parent::resolveValue();
    }
}
