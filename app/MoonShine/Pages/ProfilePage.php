<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\UniMice\Endpoints\UsersEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Card;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;

/**
 * Профиль текущего пользователя — GET /users/me.
 */
final class ProfilePage extends Page
{
    public const URI = 'profile';

    public function getUriKey(): string
    {
        return self::URI;
    }

    public function getTitle(): string
    {
        return 'Профиль';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        try {
            $me = app(UsersEndpoint::class)->me();
        } catch (UniMiceApiException $exception) {
            return [
                Alert::make(type: 'error')->content($exception->getMessage()),
            ];
        }

        return [
            Heading::make($me->fullName ?? 'Профиль'),

            Grid::make([
                Column::make([
                    Card::make('Личные данные', values: [
                        'Email' => $me->email ?? '—',
                        'Телефон' => $me->phone ?? '—',
                        'Должность' => $me->post ?? '—',
                        'В системе с' => $me->createdAt ?? '—',
                    ]),
                ])->columnSpan(6),

                Column::make([
                    Card::make('Роль в компании', values: [
                        'Роль' => $me->role?->name ?? '—',
                        'Отдел' => $me->group?->name ?? '—',
                        'Руководитель' => $me->chiefName ?? '—',
                        'Компания' => $me->clientName ?? '—',
                    ]),
                ])->columnSpan(6),
            ]),
        ];
    }
}
