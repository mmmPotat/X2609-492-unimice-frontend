<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\UniMice\Dto\EventAmountsDto;
use App\Services\UniMice\Dto\EventDto;
use App\Services\UniMice\Dto\EventFeeDto;
use App\Services\UniMice\Dto\EventUserDto;
use App\Services\UniMice\Dto\LegalEntityDto;
use App\Services\UniMice\Endpoints\EventsEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Card;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Components\Tabs;
use MoonShine\UI\Components\Tabs\Tab;
use MoonShine\UI\Fields\Text;

/**
 * Карточка заявки. Открывается из списка по ?id=N: slug маршрута
 * страницы MoonShine параметров не поддерживает, поэтому id живёт
 * в query string. При этом фильтры списка сохраняются в ссылке
 * «назад» (сервер читает referer).
 *
 * Разделы — табами на одной странице, как договорились с заказчиком:
 * общая информация, клиент, агентство, участники, услуги,
 * организационные взносы, дополнительные поля. Все данные — из одного
 * ответа GET /events/{id}.
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
        $components = [];

        if (($success = session('successMessage')) !== null) {
            $components[] = Alert::make(type: 'success')->content((string) $success);
        }

        $id = filter_var(request()->query('id'), FILTER_VALIDATE_INT);

        if ($id === false || $id <= 0) {
            return [
                ...$components,
                Alert::make(type: 'warning')->content('Откройте заявку из списка заявок'),
            ];
        }

        try {
            $event = $this->event = $this->loadEvent($id);
        } catch (UniMiceApiException $exception) {
            if ($exception->httpStatus === 404) {
                abort(404);
            }

            return [
                ...$components,
                Alert::make(type: 'error')->content($exception->getMessage()),
            ];
        }

        return [
            ...$components,
            ActionButton::make('← К списку заявок', $this->listUrl())->secondary(),
            Tabs::make([
                Tab::make('Общая информация', $this->infoTab($event)),
                Tab::make('Клиент', $this->clientTab($event)),
                Tab::make('Агентство', $this->agencyTab($event)),
                Tab::make('Участники', $this->participantsTab($event)),
                Tab::make('Услуги', $this->amenitiesTab($event)),
                Tab::make('Организационные взносы', $this->feesTab($event)),
                Tab::make('Дополнительные поля', $this->extraFieldsTab($event)),
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
     * @return list<ComponentContract>
     */
    private function infoTab(EventDto $event): array
    {
        return [
            Grid::make([
                Column::make([
                    Card::make('Параметры', values: $this->parameters($event)),
                    Card::make('Описание', values: ['Текст' => $event->description ?: '—']),
                ])->columnSpan(12),
            ]),
        ];
    }

    /**
     * @return list<ComponentContract>
     */
    private function clientTab(EventDto $event): array
    {
        $components = [];

        if ($event->clientLegalEntity !== null) {
            $components[] = Card::make('Юридическое лицо клиента', values: $this->legalEntityValues($event->clientLegalEntity));
        }

        if ($event->clientInitiator !== null) {
            $components[] = Card::make('Инициатор', values: $this->userValues($event->clientInitiator));
        }

        return $components === []
            ? [Alert::make(type: 'info')->content('Данные клиента не заполнены.')]
            : $components;
    }

    /**
     * @return list<ComponentContract>
     */
    private function agencyTab(EventDto $event): array
    {
        $components = [];

        if ($event->agencyLegalEntity !== null) {
            $components[] = Card::make('Юридическое лицо агентства', values: $this->legalEntityValues($event->agencyLegalEntity));
        }

        if ($event->agencyManager !== null) {
            $components[] = Card::make('Менеджер', values: $this->userValues($event->agencyManager));
        }

        return $components === []
            ? [Alert::make(type: 'info')->content('Данные агентства не заполнены.')]
            : $components;
    }

    /**
     * @return list<ComponentContract>
     */
    private function participantsTab(EventDto $event): array
    {
        return [
            Card::make('Участники', values: [
                'Количество участников' => (string) $event->participantsCount,
            ]),
        ];
    }

    /**
     * Услуги: форма элемента в ответе API не зафиксирована (обычно
     * пустой список), поэтому выводим только известные поля.
     *
     * @return list<ComponentContract>
     */
    private function amenitiesTab(EventDto $event): array
    {
        if ($event->eventAmenities === []) {
            return [Alert::make(type: 'info')->content('Услуги не добавлены.')];
        }

        $rows = [];

        foreach ($event->eventAmenities as $amenity) {
            if (! is_array($amenity)) {
                continue;
            }

            $name = $amenity['name'] ?? $amenity['title'] ?? null;
            $supplier = $amenity['supplierName'] ?? $amenity['supplier'] ?? null;

            if (is_array($supplier)) {
                $supplier = $supplier['name'] ?? null;
            }

            $rows[] = [
                'name' => is_scalar($name) ? (string) $name : '—',
                'supplier' => is_scalar($supplier) ? (string) $supplier : '—',
                'amount' => isset($amenity['amount']) && is_numeric($amenity['amount'])
                    ? number_format((float) $amenity['amount'], 2, ',', ' ')
                    : '—',
            ];
        }

        if ($rows === []) {
            return [Alert::make(type: 'info')->content('Услуги не добавлены.')];
        }

        return [
            TableBuilder::make()
                ->items($rows)
                ->fields([
                    Text::make('Услуга', 'name'),
                    Text::make('Поставщик', 'supplier'),
                    Text::make('Сумма', 'amount'),
                ]),
        ];
    }

    /**
     * @return list<ComponentContract>
     */
    private function feesTab(EventDto $event): array
    {
        $components = [];
        $fees = $event->eventFees?->all() ?? [];

        if ($fees === []) {
            $components[] = Alert::make(type: 'info')->content('Организационные взносы не добавлены.');
        } else {
            $rows = array_map(static fn (EventFeeDto $fee): array => [
                'name' => $fee->name,
                'amount' => number_format($fee->amount, 2, ',', ' ').' ₽',
                'vatRate' => $fee->vatRate !== null ? $fee->vatRate.' %' : '—',
                'description' => $fee->description ?: '—',
                'createdAt' => $fee->createdAt?->format('d.m.Y') ?? '—',
            ], $fees);

            $components[] = TableBuilder::make()
                ->items($rows)
                ->fields([
                    Text::make('Название', 'name'),
                    Text::make('Сумма', 'amount'),
                    Text::make('НДС', 'vatRate'),
                    Text::make('Описание', 'description'),
                    Text::make('Создан', 'createdAt'),
                ]);
        }

        if ($event->amountSummaries !== null) {
            $components[] = Card::make('Суммы', values: $this->amountValues($event->amountSummaries));
        }

        return $components;
    }

    /**
     * @return array<string, string>
     */
    private function amountValues(EventAmountsDto $amounts): array
    {
        return [
            'Услуги (с НДС)' => $amounts->totalAmountServices !== null ? $amounts->totalAmountServices->format($amounts->totalAmountServices->withVat) : '—',
            'Взносы (с НДС)' => $amounts->totalAmountFees !== null ? $amounts->totalAmountFees->format($amounts->totalAmountFees->withVat) : '—',
            'Итого (с НДС)' => $amounts->totalAmount !== null ? $amounts->totalAmount->format($amounts->totalAmount->withVat) : '—',
            'Итого без НДС' => $amounts->totalAmount !== null ? $amounts->totalAmount->format($amounts->totalAmount->withoutVat) : '—',
            'НДС' => $amounts->totalAmount !== null ? $amounts->totalAmount->format($amounts->totalAmount->vat) : '—',
        ];
    }

    /**
     * @return list<ComponentContract>
     */
    private function extraFieldsTab(EventDto $event): array
    {
        $fields = $event->extraFields?->all() ?? [];

        if ($fields === []) {
            return [Alert::make(type: 'info')->content('Дополнительные поля не заполнены.')];
        }

        $rows = array_map(static fn ($field): array => [
            'name' => $field->name,
            'value' => $field->displayValue(),
            'required' => $field->required ? 'Да' : 'Нет',
        ], $fields);

        return [
            TableBuilder::make()
                ->items($rows)
                ->fields([
                    Text::make('Поле', 'name'),
                    Text::make('Значение', 'value'),
                    Text::make('Обязательное', 'required'),
                ]),
        ];
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
            'Поставщик' => $event->supplierName ?? '—',
            'Участники' => $event->participantsCount,
            'Создана' => $event->createdAt?->format('d.m.Y H:i') ?? '—',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function legalEntityValues(LegalEntityDto $legalEntity): array
    {
        return [
            'Название' => $legalEntity->name,
            'ИНН' => $legalEntity->inn ?? '—',
            'КПП' => $legalEntity->kpp ?? '—',
            'Адрес' => $legalEntity->address ?? '—',
            'Ставка НДС' => $legalEntity->vatRate !== null ? $legalEntity->vatRate.' %' : '—',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function userValues(EventUserDto $user): array
    {
        return [
            'ФИО' => $user->fullName(),
            'Email' => $user->email ?? '—',
            'Телефон' => $user->phone ?? '—',
            'Должность' => $user->post ?? '—',
            'Роль' => $user->role?->name ?? '—',
            'Клиент' => $user->client?->name ?? '—',
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
