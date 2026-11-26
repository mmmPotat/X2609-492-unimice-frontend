<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Support\PagePaginator;
use App\Services\UniMice\Dto\SuppliersResultDto;
use App\Services\UniMice\Dto\SupplierTypeDto;
use App\Services\UniMice\Endpoints\EventsEndpoint;
use App\Services\UniMice\Endpoints\SuppliersEndpoint;
use App\Services\UniMice\UniMiceApiException;
use Illuminate\Support\Facades\Cache;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Components\Paginator as PaginatorComponent;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Enums\FormMethod;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Card;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Divider;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Fields\Checkbox;
use MoonShine\UI\Fields\Number;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use Spatie\LaravelData\DataCollection;
use Throwable;

/**
 * Каталог площадок — поставщики типов «площадка» (GET /suppliers).
 *
 * Отображение — карточки, как в Appsmith; фильтры повторяют Appsmith:
 * звёздность, название, типы, город, адрес, стоимость/площадь/
 * вместительность от–до, только избранные. Всё серверное, состояние
 * фильтров живёт в GET-параметрах формы. Колонку статуса заказчик
 * убрать попросил — не выводим.
 */
final class VenuesPage extends Page
{
    public const URI = 'venues';

    private const TYPES_CACHE_KEY = 'unimice.suppliers.types';

    /** Допустимые значения «записей на странице» (селектор limit). */
    private const PER_PAGE_OPTIONS = [12, 24, 48];

    public function getUriKey(): string
    {
        return self::URI;
    }

    public function getTitle(): string
    {
        return 'Каталог площадок';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $filters = $this->filters();
        $page = PagePaginator::currentPage();
        $perPage = $this->perPage();

        try {
            $result = $this->fetch($filters, $page, $perPage);

            // ?page= за пределами выборки: API честно вернёт пустую
            // страницу — перезапрашиваем последнюю, чтобы показать данные.
            if ($page > 1 && count($result->items) === 0 && $result->total > 0) {
                $page = PagePaginator::boundedPage($result->total, $perPage);
                $result = $this->fetch($filters, $page, $perPage);
            }
        } catch (UniMiceApiException $exception) {
            return [
                Alert::make(type: 'error')->content($exception->getMessage()),
            ];
        }

        $components = [
            Heading::make('Каталог площадок ('.$result->total.')'),

            $this->filterForm($filters, $perPage),
        ];

        if (count($result->items) === 0) {
            $components[] = Alert::make(type: 'info')->content('Ничего не найдено. Измените фильтры или запрос.');

            return $components;
        }

        $cards = [];

        foreach ($result->items as $supplier) {
            $values = [
                'Город' => $supplier->city?->name ?? '—',
                'Адрес' => trim((string) $supplier->address) ?: '—',
                'Звёзды' => $supplier->stars !== null ? str_repeat('★', $supplier->stars) : 'без звёзд',
            ];

            if ($supplier->isFavorite) {
                $values['Избранное'] = '♥ в избранном';
            }

            $cards[] = Column::make([
                Card::make(
                    title: $supplier->name,
                    url: '#',
                    values: $values,
                    subtitle: $supplier->type?->name,
                ),
            ], colSpan: 4, adaptiveColSpan: 12);
        }

        $components[] = Grid::make($cards, gap: 4);
        $components[] = $this->paginatorComponent(
            array_fill(0, count($result->items), []),
            $result->total,
            $perPage,
            $page,
        );

        return $components;
    }

    /** Запрос к API с набором фильтров. */
    private function fetch(array $filters, int $page, int $perPage): SuppliersResultDto
    {
        return app(SuppliersEndpoint::class)->list([
            ...$filters,
            'page' => $page,
            'limit' => $perPage,
        ]);
    }

    /**
     * Фильтр-панель: форма GET, как в Appsmith. Значения читаются
     * из query и подставляются обратно, чтобы форма не сбрасывалась.
     */
    private function filterForm(array $filters, int $perPage): ComponentContract
    {
        return FormBuilder::make()
            ->action(url('/admin/page/'.self::URI))
            ->method(FormMethod::GET)
            ->fields([
                // Раскладка повторяет Appsmith: основные фильтры — рядом
                // в 5 колонок, числовые — в секции «Дополнительные
                // параметры» двумя рядами «от» и «до».
                Grid::make([
                    Column::make([Text::make('Название', 'name')], colSpan: 3, adaptiveColSpan: 6),
                    Column::make([
                        Select::make('Типы', 'type')
                            ->multiple()
                            ->options($this->typeOptions()),
                    ], colSpan: 3, adaptiveColSpan: 6),
                    Column::make([$this->cityField()], colSpan: 2, adaptiveColSpan: 6),
                    Column::make([Text::make('Адрес', 'address')], colSpan: 2, adaptiveColSpan: 6),
                    Column::make([
                        Select::make('Звёздность', 'stars')
                            ->multiple()
                            ->options([
                                5 => '★★★★★',
                                4 => '★★★★',
                                3 => '★★★',
                                2 => '★★',
                                1 => '★',
                                0 => 'Без звёзд',
                            ]),
                    ], colSpan: 2, adaptiveColSpan: 6),
                ], gap: 2),

                Divider::make(),

                // Числовые фильтры — как в Appsmith: три пары «от/до»
                // столбиками, справа чекбокс избранных и селектор
                // «на странице».
                Grid::make([
                    Column::make([
                        Number::make('Вместительность от', 'capacityMin'),
                        Number::make('Вместительность до', 'capacityMax'),
                    ], colSpan: 2, adaptiveColSpan: 6),
                    Column::make([
                        Number::make('Площадь от, м²', 'sizeMin'),
                        Number::make('Площадь до, м²', 'sizeMax'),
                    ], colSpan: 2, adaptiveColSpan: 6),
                    Column::make([
                        Number::make('Стоимость от', 'priceMin'),
                        Number::make('Стоимость до', 'priceMax'),
                    ], colSpan: 2, adaptiveColSpan: 6),
                    Column::make([
                        Checkbox::make('Только избранные', 'isFavorite')
                            ->wrapperClass('unimice-filter-check'),
                        Select::make('На странице', 'limit')
                            ->options([
                                12 => '12',
                                24 => '24',
                                48 => '48',
                            ])
                            ->wrapperClass('unimice-filter-limit'),
                    ], colSpan: 4, adaptiveColSpan: 6),
                ], gap: 4),
            ])
            ->fill([
                'name' => $filters['name'],
                'address' => $filters['address'],
                'type' => $filters['typeSelected'],
                'stars' => $filters['stars'],
                'cityId' => $filters['cityId'],
                'priceMin' => $filters['priceMin'],
                'priceMax' => $filters['priceMax'],
                'sizeMin' => $filters['sizeMin'],
                'sizeMax' => $filters['sizeMax'],
                'capacityMin' => $filters['capacityMin'],
                'capacityMax' => $filters['capacityMax'],
                'isFavorite' => $filters['isFavorite'],
                'limit' => $perPage,
            ])
            ->buttons([
                ActionButton::make('Сбросить', url('/admin/page/'.self::URI))->secondary(),
            ])
            ->submit('Найти');
    }

    /** Город: async-select (поиск по вводу), как в форме создания заявки. */
    private function cityField(): Select
    {
        $options = [];

        $cityId = filter_var(request()->query('cityId'), FILTER_VALIDATE_INT);

        if ($cityId !== false && $cityId > 0) {
            try {
                $city = app(EventsEndpoint::class)->city($cityId);
                $options[$city->id] = $city->name;
            } catch (Throwable) {
                // город не найден — оставляем пустым
            }
        }

        return Select::make('Город', 'cityId')
            ->options($options)
            ->async(route('admin.event-create.cities'))
            ->nullable();
    }

    /** Типы-площадки из кешированного справочника (не-площадки отбрасываем). */
    private function typeOptions(): array
    {
        try {
            $items = Cache::remember(
                self::TYPES_CACHE_KEY,
                now()->addHour(),
                fn (): array => app(SuppliersEndpoint::class)->types()->toArray(),
            );

            /** @var DataCollection<int, SupplierTypeDto> $types */
            $types = SupplierTypeDto::collect($items, DataCollection::class);
        } catch (Throwable) {
            return [];
        }

        $options = [];

        foreach ($types as $type) {
            if (in_array($type->id, SuppliersEndpoint::VENUE_TYPE_IDS, true)) {
                $options[$type->id] = $type->name;
            }
        }

        return $options;
    }

    /**
     * Чтение фильтров из GET-параметров. Мусор отбрасываем,
     * числа приводим к типам, пустое — в null.
     */
    private function filters(): array
    {
        $query = request()->query();

        return [
            'name' => $this->string($query['name'] ?? null),
            'address' => $this->string($query['address'] ?? null),
            'cityId' => $this->int($query['cityId'] ?? null),
            // В API уходит дефолт «все типы-площадки», когда ?type= не передан,
            // но в форму это значение подставлять нельзя — иначе мультиселект
            // подсвечивает все теги. Храним выбор пользователя отдельно.
            'typeIds' => $this->intList($query['type'] ?? null, SuppliersEndpoint::VENUE_TYPE_IDS),
            'typeSelected' => $this->intList($query['type'] ?? null, null) ?? [],
            'stars' => $this->intList($query['stars'] ?? null, null),
            'priceMin' => $this->floatOrNull($query['priceMin'] ?? null),
            'priceMax' => $this->floatOrNull($query['priceMax'] ?? null),
            'sizeMin' => $this->int($query['sizeMin'] ?? null),
            'sizeMax' => $this->int($query['sizeMax'] ?? null),
            'capacityMin' => $this->int($query['capacityMin'] ?? null),
            'capacityMax' => $this->int($query['capacityMax'] ?? null),
            'isFavorite' => filter_var($query['isFavorite'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /** Записей на странице из ?limit= (только допустимые значения). */
    private function perPage(): int
    {
        $limit = filter_var(request()->query('limit'), FILTER_VALIDATE_INT);

        return in_array($limit, self::PER_PAGE_OPTIONS, true) ? $limit : 12;
    }

    /** Отдельный блок пагинации под сеткой карточек. */
    private function paginatorComponent(array $items, int $total, int $perPage, int $page): ComponentContract
    {
        return PaginatorComponent::make(
            PagePaginator::laravelPaginator($items, $total, $perPage, $page),
        );
    }

    private function string(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function int(mixed $value): ?int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);

        return $value === false ? null : $value;
    }

    private function floatOrNull(mixed $value): ?float
    {
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);

        return $value === false ? null : $value;
    }

    /**
     * Список целых из массива-параметра (?stars[]=2). Если параметр
     * не передан — дефолт (для типов это все типы-площадки).
     *
     * @return list<int>|null
     */
    private function intList(mixed $value, ?array $default): ?array
    {
        if ($value === null) {
            return $default;
        }

        $ids = [];

        foreach ((array) $value as $item) {
            $id = filter_var($item, FILTER_VALIDATE_INT);

            if ($id !== false && $id >= 0) {
                $ids[] = $id;
            }
        }

        $ids = array_values(array_unique($ids));

        return $ids === [] ? $default : $ids;
    }
}
