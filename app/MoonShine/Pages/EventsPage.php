<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Support\EventStatusColor;
use App\Services\UniMice\Dto\EventStatusDto;
use App\Services\UniMice\Endpoints\EventsEndpoint;
use App\Services\UniMice\UniMiceApiException;
use Illuminate\Support\Facades\Cache;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Text;
use Spatie\LaravelData\DataCollection;
use Throwable;

/**
 * Список заявок на мероприятия — данные напрямую из API UniMICE,
 * без промежуточного хранения в локальной БД.
 *
 * Фильтрация — серверная, без JS: выбранные статусы живут в GET-параметре
 * ?status[]=2&status[]=6, страница читает его и пробрасывает в API.
 */
final class EventsPage extends Page
{
    private const STATUSES_CACHE_KEY = 'unimice.events.statuses';

    public function getTitle(): string
    {
        return $this->title ?: 'Заявки';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $activeStatuses = $this->activeStatuses();

        try {
            $events = app(EventsEndpoint::class)->list(
                $activeStatuses === [] ? [] : ['statusIds' => $activeStatuses],
            );
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

        $components = [];

        $statusBar = $this->statusBar($activeStatuses);

        if ($statusBar !== null) {
            $components[] = $statusBar;
        }

        $components[] = TableBuilder::make()
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
            ]);

        return $components;
    }

    /**
     * Панель фильтров: чип статуса — ссылка, клик добавляет статус в
     * выборку, повторный клик — убирает. Кнопка «Все» сбрасывает фильтр.
     * Справочник кешируется на час: это маленький ответ, но лишний
     * запрос к API стоит секунд.
     */
    private function statusBar(array $activeStatuses): ?Flex
    {
        // Кешируем plain-массив, а не DataCollection: объекты spatie-data
        // с анонимными классами внутри не переживают serialize кеша.
        try {
            $items = Cache::remember(
                self::STATUSES_CACHE_KEY,
                now()->addHour(),
                fn (): array => app(EventsEndpoint::class)->statuses()->toArray(),
            );

            $statuses = EventStatusDto::collect($items, DataCollection::class);
        } catch (Throwable) {
            return null;
        }

        $buttons = [$this->statusChip('Все', [], $activeStatuses === [])];

        foreach ($statuses as $status) {
            $isActive = in_array($status->id, $activeStatuses, true);

            $next = $isActive
                ? array_values(array_diff($activeStatuses, [$status->id]))
                : [...$activeStatuses, $status->id];

            $buttons[] = $this->statusChip($status->name, $next, $isActive);
        }

        return Flex::make($buttons, justifyAlign: 'start');
    }

    private function statusChip(string $label, array $statusIds, bool $isActive): ActionButton
    {
        $button = ActionButton::make($label, $this->filterUrl($statusIds));

        return $isActive ? $button->primary() : $button->secondary();
    }

    /** Ссылка на страницу с заданным набором статусов. */
    private function filterUrl(array $statusIds): string
    {
        $statusIds = array_values(array_unique($statusIds));

        return $statusIds === []
            ? request()->url()
            : request()->url().'?'.http_build_query(['status' => $statusIds]);
    }

    /** ID статусов, выбранных в GET-параметре ?status[]. */
    private function activeStatuses(): array
    {
        $ids = [];

        foreach ((array) request()->query('status', []) as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);

            if ($id !== false && $id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
