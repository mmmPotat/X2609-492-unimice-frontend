<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\UniMice\Endpoints\EventsEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Enums\FormMethod;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Fields\Date;
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
 * Город подбирается без JS: пользователь вводит подстроку и жмёт
 * «Создать заявку» — контроллер вместо создания возвращает форму
 * со списком найденных городов, один из них нужно выбрать и нажать
 * ещё раз.
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

        $cityOptions = $this->cityOptions();

        if ($cityOptions !== []) {
            $components[] = Alert::make(type: 'info')
                ->content('Выберите город из списка и нажмите «Создать заявку» ещё раз.');
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

        $fields[] = Text::make('Город (введите название и нажмите «Создать заявку» для подбора)', 'cityQuery');

        if ($cityOptions !== []) {
            $fields[] = Select::make('Выбранный город', 'cityId')
                ->options($cityOptions)
                ->required();
        } else {
            $fields[] = Hidden::make('cityId', 'cityId');
        }

        $fields[] = Number::make('Количество участников', 'participantsCount')->required()->min(1);
        $fields[] = Date::make('Начало', 'startDate')->required()->withTime();
        $fields[] = Date::make('Завершение', 'finishDate')->required()->withTime();
        $fields[] = Textarea::make('Комментарий к заявке', 'description');

        $components[] = Heading::make($this->getTitle());
        $components[] = FormBuilder::make()
            ->action(route('admin.event-create', ['category' => $isFee ? self::CATEGORY_FEE : self::CATEGORY_EVENT]))
            ->method(FormMethod::POST)
            ->fields($fields)
            ->fill(old() ?: [])
            ->submit('Создать заявку');

        return $components;
    }

    private function isFee(): bool
    {
        return request()->query('category') === self::CATEGORY_FEE;
    }

    /**
     * Варианты городов, найденные контроллером на прошлом шаге.
     *
     * @return array<int, string>
     */
    private function cityOptions(): array
    {
        $options = session('cityOptions');

        return is_array($options) ? $options : [];
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
