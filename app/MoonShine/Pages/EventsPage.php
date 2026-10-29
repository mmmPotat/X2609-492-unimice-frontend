<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Support\EventStatusColor;
use App\Services\UniMice\Endpoints\EventsEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;

/**
 * Список заявок на мероприятия — данные напрямую из API UniMICE,
 * без промежуточного хранения в локальной БД.
 */
final class EventsPage extends Page
{
    public function getTitle(): string
    {
        return $this->title ?: 'Заявки';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        try {
            $events = app(EventsEndpoint::class)->list();
        } catch (UniMiceApiException $exception) {
            return [
                Alert::make($exception->getMessage(), 'Ошибка API')->error(),
            ];
        }

        $rows = [];

        foreach ($events as $event) {
            $rows[] = [
                'id' => $event->id,
                'title' => $event->title,
                'statusId' => $event->status->id,
                'status' => $event->status->name,
                'type' => $event->type->name,
                'city' => $event->city?->name ?? '—',
                'startDate' => $event->startDate?->format('d.m.Y H:i') ?? '—',
                'participantsCount' => $event->participantsCount,
            ];
        }

        return [
            TableBuilder::make()
                ->items($rows)
                ->fields([
                    Text::make('ID', 'id'),
                    Text::make('Название', 'title'),
                    Text::make('Статус', 'status')
                        ->badge(static fn ($value, $field) => EventStatusColor::byId(
                            (int) ($field->getData()?->toArray()['statusId'] ?? 0),
                        )),
                    Text::make('Тип', 'type'),
                    Text::make('Город', 'city'),
                    Text::make('Начало', 'startDate'),
                    Number::make('Участники', 'participantsCount'),
                ]),
        ];
    }
}
