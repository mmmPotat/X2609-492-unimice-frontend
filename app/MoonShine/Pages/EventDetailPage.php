<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\UniMice\Dto\EventDto;
use App\Services\UniMice\Endpoints\EventsEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Card;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;

/**
 * Карточка заявки. Открывается из списка по ?id=N: slug маршрута
 * страницы MoonShine параметров не поддерживает, поэтому id живёт
 * в query string. При этом фильтры списка сохраняются в ссылке
 * «назад» (сервер читает referer).
 */
final class EventDetailPage extends Page
{
    /** Slug маршрута страницы (используется и списком заявок). */
    public const URI = 'event-detail';

    private const LIST_URL = '/admin/page/events-page';

    private ?EventDto $event = null;

    public function getUriKey(): string
    {
        return self::URI;
    }

    public function getTitle(): string
    {
        return $this->event()?->title ?? 'Заявка';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $id = filter_var(request()->query('id'), FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0) {
            return [
                Alert::make('Откройте заявку из списка заявок', 'Не указана заявка')->warning(),
            ];
        }

        try {
            $event = $this->event = $this->loadEvent($id);
        } catch (UniMiceApiException $exception) {
            if ($exception->httpStatus === 404) {
                abort(404);
            }

            return [
                Alert::make($exception->getMessage(), 'Ошибка API')->error(),
            ];
        }

        return [
            ActionButton::make('← К списку заявок', $this->listUrl())->secondary(),
            Heading::make($event->title),
            Grid::make([
                Column::make([
                    Card::make('Параметры', values: $this->parameters($event)),
                    Card::make('Описание', values: ['Текст' => $event->description ?: '—']),
                ])->columnSpan(12),
            ]),
        ];
    }

    private function loadEvent(int $id): EventDto
    {
        return app(EventsEndpoint::class)->get($id);
    }

    /** Ленивая загрузка для заголовка страницы (запросит API один раз). */
    private function event(): ?EventDto
    {
        if ($this->event !== null) {
            return $this->event;
        }

        $id = filter_var(request()->query('id'), FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0) {
            return null;
        }

        try {
            return $this->event = $this->loadEvent($id);
        } catch (UniMiceApiException) {
            return null;
        }
    }

    /**
     * @return array<string, string|int>
     */
    private function parameters(EventDto $event): array
    {
        return [
            'Статус' => $event->status->name,
            'Тип' => $event->type->name,
            'Категория' => $event->category->name,
            'Город' => $event->city?->name ?? '—',
            'Начало' => $event->startDate?->format('d.m.Y H:i') ?? '—',
            'Окончание' => $event->finishDate?->format('d.m.Y H:i') ?? '—',
            'Площадка' => $event->venue ?? '—',
            'Участники' => $event->participantsCount,
            'Создана' => $event->createdAt?->format('d.m.Y H:i') ?? '—',
        ];
    }

    private function listUrl(): string
    {
        $referer = request()->headers->get('referer');

        // вернуться туда же, откуда открыли карточку (с сохранением фильтров)
        if (is_string($referer) && str_contains($referer, self::LIST_URL)) {
            return $referer;
        }

        return url(self::LIST_URL);
    }
}
