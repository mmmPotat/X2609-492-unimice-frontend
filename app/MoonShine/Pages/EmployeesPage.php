<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\MoonShine\Support\PagePaginator;
use App\Services\UniMice\Dto\UsersResultDto;
use App\Services\UniMice\Endpoints\UsersEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Crud\Components\Paginator as PaginatorComponent;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Enums\FormMethod;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Text;

/**
 * Сотрудники агентства — GET /users.
 *
 * Поиск и пагинация серверные, без JS: строка поиска живёт в ?query=,
 * страница — в ?page=, оба параметра пробрасываются в API
 * (API ищет по ФИО/email, нарезает выборку по page/limit).
 */
final class EmployeesPage extends Page
{
    public const URI = 'employees';

    public function getUriKey(): string
    {
        return self::URI;
    }

    public function getTitle(): string
    {
        return 'Сотрудники';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $query = trim((string) request()->query('query', ''));
        $page = PagePaginator::currentPage();
        $perPage = 25;

        $filters = $query === '' ? [] : ['query' => $query];

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

        $rows = [];

        foreach ($result->items as $user) {
            $rows[] = [
                'fullName' => $user->fullName,
                'post' => $user->post ?: '—',
                'role' => $user->role?->name ?? '—',
                'group' => $user->group?->name ?? '—',
                'chiefName' => $user->chiefName ?? '—',
                'phone' => $user->phone ?: '—',
                'email' => $user->email,
            ];
        }

        $components = [
            Heading::make('Сотрудники ('.$result->total.')'),

            FormBuilder::make()
                ->action(url('/admin/page/'.self::URI))
                ->method(FormMethod::GET)
                ->fields([
                    Text::make('Поиск', 'query')->placeholder('ФИО или email'),
                ])
                ->fill(['query' => $query])
                ->submit('Найти'),

            TableBuilder::make()
                ->items($rows)
                ->fields([
                    Text::make('ФИО', 'fullName'),
                    Text::make('Должность', 'post'),
                    Text::make('Роль', 'role'),
                    Text::make('Отдел', 'group'),
                    Text::make('Руководитель', 'chiefName'),
                    Text::make('Телефон', 'phone'),
                    Text::make('Email', 'email'),
                ]),

            PaginatorComponent::make(
                PagePaginator::laravelPaginator($rows, $result->total, $perPage, $page),
            ),
        ];

        // пустая таблица неочевидна — говорим об этом прямо
        if ($rows === []) {
            $components[] = Alert::make(type: 'info')->content('Никого не найдено. Измените запрос.');
        }

        return $components;
    }

    /**
     * @param  array{query?: string}  $filters
     */
    private function fetch(array $filters, int $page, int $perPage): UsersResultDto
    {
        return app(UsersEndpoint::class)->list([
            ...$filters,
            'page' => $page,
            'limit' => $perPage,
        ]);
    }
}
