<?php

declare(strict_types=1);

namespace App\MoonShine\Support;

use Illuminate\Pagination\LengthAwarePaginator;
use MoonShine\Contracts\Core\Paginator\PaginatorContract;
use MoonShine\Crud\TypeCasts\PaginatorCaster;

/**
 * Сборка MoonShine-пагинатора из готового набора строк.
 *
 * Используется на экранах, где данные приходят из API UniMICE
 * (а не из Eloquent): строки уже сформированы, остаётся нарезать
 * страницу и построить ссылки. Текущие GET-параметры (фильтры)
 * сохраняются в ссылках, ?page= заменяется.
 */
final class PagePaginator
{
    /**
     * @param  list<array<string, mixed>>  $items  строки текущей страницы
     * @param  int  $total  всего записей по выборке
     * @param  int  $perPage  записей на странице
     * @param  int  $page  текущая страница (?page=, минимум 1)
     */
    public static function make(array $items, int $total, int $perPage, int $page): PaginatorContract
    {
        $laravelPaginator = self::laravelPaginator($items, $total, $perPage, $page);

        return (new PaginatorCaster(
            $laravelPaginator->appends(request()->except('page'))->toArray(),
            $items,
        ))->cast();
    }

    /**
     * Голый Laravel-пагинатор (для MoonShine\Crud\Components\Paginator,
     * который сам конвертирует его и добавляет query-параметры).
     */
    public static function laravelPaginator(array $items, int $total, int $perPage, int $page): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            items: $items,
            total: $total,
            perPage: $perPage,
            currentPage: max(1, $page),
            options: ['path' => request()->url()],
        );
    }

    /** Текущая страница из ?page= (минимум 1, мусор → 1). */
    public static function currentPage(): int
    {
        return max(1, (int) request()->query('page', 1));
    }

    /**
     * Текущая страница, прижатая к реальному диапазону [1, lastPage].
     * Защищает от ?page= за пределами выборки (пустая таблица
     * со счётчиком «1 to 1» вместо данных).
     */
    public static function boundedPage(int $total, int $perPage): int
    {
        $lastPage = max(1, (int) ceil($total / $perPage));

        return min(self::currentPage(), $lastPage);
    }
}
