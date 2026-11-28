<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Fields\SafeDate;
use App\Services\UniMice\Endpoints\EventsEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Enums\FormMethod;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Fields\Hidden;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

/**
 * Создание заявки (экран «Новое мероприятие» / «Новый орг. взнос»).
 *
 * Одна страница, два режима через ?category=event|fee: у орг. взноса
 * тип и категория фиксированы. Шаги «Площадка/Зал/Оборудование» из
 * веб-примера появятся позже — они завязаны на каталог площадок.
 *
 * Город — async-select: варианты подгружаются с сервера по мере
 * ввода (GET /admin/event-create/cities?query=...), поиск делает
 * API через GET /cities/search.
 */
final class EventCreatePage extends Page
{
    public const URI = 'event-create';

    private const CATEGORY_EVENT = 'event';

    private const CATEGORY_FEE = 'fee';

    /** Тип «Орг. взнос» (из справочника GET /events/types). */
    private const FEE_TYPE_ID = 29;

    public function getUriKey(): string
    {
        return self::URI;
    }

    public function getTitle(): string
    {
        return $this->isFee() ? 'Новый орг. взнос' : 'Новое мероприятие';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $isFee = $this->isFee();
        $components = [];

        if (($apiError = session('apiError')) !== null) {
            $components[] = Alert::make(type: 'error')->content((string) $apiError);
        }

        if ($isFee) {
            $components[] = Alert::make(type: 'info')->content('Тип заявки фиксирован: «Орг. взнос»');
        }

        $fields = [
            Text::make('Название', 'title')->required(),
        ];

        if (! $isFee) {
            $fields[] = Select::make('Тип мероприятия', 'typeId')
                ->options($this->typeOptions())
                ->required()
                ->searchable();
        } else {
            $fields[] = Hidden::make('typeId', 'typeId');
        }

        $fields[] = Select::make('Город', 'cityId')
            ->options($this->selectedCityOptions())
            ->async(route('admin.event-create.cities'))
            ->required();

        $fields[] = Number::make('Количество участников', 'participantsCount')->required()->min(1);
        // Бэкенд платформы при создании учитывает только календарный день —
        // время не спрашиваем, формат Y-m-d.
        $fields[] = SafeDate::make('Начало', 'startDate')->required()->format('Y-m-d');
        $fields[] = SafeDate::make('Завершение', 'finishDate')->required()->format('Y-m-d');
        $fields[] = Textarea::make('Комментарий к заявке', 'description');

        $components[] = Heading::make($this->getTitle());
        $components[] = FormBuilder::make()
            ->action(route('admin.event-create', ['category' => $isFee ? self::CATEGORY_FEE : self::CATEGORY_EVENT]))
            ->method(FormMethod::POST)
            ->fields($fields)
            ->submit('Создать заявку');

        return $components;
    }

    private function isFee(): bool
    {
        return request()->query('category') === self::CATEGORY_FEE;
    }

    /**
     * Подпись уже выбранного города (рендер формы после ошибки
     * валидации): иначе select с async-опциями покажет «пустое»
     * выбранное значение — label приезжает только из поиска.
     *
     * @return array<int, string>
     */
    private function selectedCityOptions(): array
    {
        $cityId = filter_var(old('cityId'), FILTER_VALIDATE_INT);

        if ($cityId === false || $cityId <= 0) {
            return [];
        }

        try {
            $city = app(EventsEndpoint::class)->city($cityId);
        } catch (UniMiceApiException) {
            return [];
        }

        return [$city->id => $city->name];
    }

    /**
     * @return array<int, string>
     */
    private function typeOptions(): array
    {
        try {
            $types = app(EventsEndpoint::class)->types();
        } catch (UniMiceApiException) {
            return [];
        }

        $options = [];

        foreach ($types as $type) {
            $options[$type->id] = $type->name;
        }

        return $options;
    }

    public static function feeTypeId(): int
    {
        return self::FEE_TYPE_ID;
    }
}
