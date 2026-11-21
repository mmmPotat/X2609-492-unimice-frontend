<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\UniMice\Endpoints\UsersEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
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
 * Поиск серверный, без JS: строка поиска живёт в GET-параметре ?query=,
 * страница пробрасывает её в API как есть (API ищет по ФИО/email).
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

        try {
            $result = app(UsersEndpoint::class)->list(
                $query === '' ? [] : ['query' => $query],
            );
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
        ];

        // пустая таблица неочевидна — говорим об этом прямо
        if ($rows === []) {
            $components[] = Alert::make(type: 'info')->content('Никого не найдено. Измените запрос.');
        }

        return $components;
    }
}
