<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\UniMice\Endpoints\UsersEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Enums\FormMethod;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Card;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Text;

/**
 * Профиль текущего пользователя — GET /users/me.
 *
 * Три блока: личные данные, роль в компании, прикреплённые клиенты
 * (GET /users/{id}/clients). Ниже — форма редактирования
 * (PATCH /users через ProfileUpdateController): имя, отчество,
 * должность, телефон.
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

        $components = [];

        if (($success = session('successMessage')) !== null) {
            $components[] = Alert::make(type: 'success')->content((string) $success);
        }

        if (($apiError = session('apiError')) !== null) {
            $components[] = Alert::make(type: 'error')->content((string) $apiError);
        }

        $components[] = Heading::make($me->fullName ?? 'Профиль');

        $components[] = Grid::make([
            Column::make([
                Card::make('Личные данные', values: [
                    'Email' => $me->email ?? '—',
                    'Телефон' => $me->phone ?? '—',
                    'Должность' => $me->post ?? '—',
                    'В системе с' => $me->createdAt ?? '—',
                ]),
            ])->columnSpan(4),

            Column::make([
                Card::make('Роль в компании', values: [
                    'Роль' => $me->role?->name ?? '—',
                    'Отдел' => $me->group?->name ?? '—',
                    'Руководитель' => $me->chiefName ?? '—',
                    'Компания' => $me->clientName ?? '—',
                ]),
            ])->columnSpan(4),

            Column::make($this->clientsComponents($me->id))->columnSpan(4),
        ]);

        $components[] = Heading::make('Редактирование профиля');

        $components[] = FormBuilder::make()
            ->action(route('admin.profile-update'))
            ->method(FormMethod::POST)
            ->fields([
                Grid::make([
                    Column::make([Text::make('Имя', 'firstName')->required()], colSpan: 4),
                    Column::make([Text::make('Фамилия', 'lastName')->required()], colSpan: 4),
                    Column::make([Text::make('Отчество', 'middleName')], colSpan: 4),
                    Column::make([Text::make('Должность', 'post')], colSpan: 6),
                    Column::make([Text::make('Телефон', 'phone')], colSpan: 6),
                ]),
            ])
            ->fill([
                'firstName' => old('firstName', $me->firstName),
                'lastName' => old('lastName', $me->lastName),
                'middleName' => old('middleName', $me->middleName),
                'post' => old('post', $me->post),
                'phone' => old('phone', $me->phone),
            ])
            ->submit('Сохранить');

        return $components;
    }

    /**
     * Прикреплённые клиенты; сбой запроса не должен ронять всю страницу.
     *
     * @return list<ComponentContract>
     */
    private function clientsComponents(int $userId): array
    {
        $components = [Heading::make('Прикреплённые клиенты')];

        try {
            $clients = app(UsersEndpoint::class)->clients($userId);
        } catch (UniMiceApiException $exception) {
            $components[] = Alert::make(type: 'error')
                ->content('Не удалось загрузить клиентов: '.$exception->getMessage());

            return $components;
        }

        $rows = [];

        foreach ($clients as $client) {
            $rows[] = [
                'name' => $client->name,
                'type' => $client->typeLabel ?? '—',
                'requestEmail' => $client->requestEmail ?: '—',
            ];
        }

        if ($rows === []) {
            $components[] = Alert::make(type: 'info')->content('Клиенты не прикреплены.');

            return $components;
        }

        $components[] = TableBuilder::make()
            ->items($rows)
            ->fields([
                Text::make('Название', 'name'),
                Text::make('Тип', 'type'),
                Text::make('Email для заявок', 'requestEmail'),
            ]);

        return $components;
    }
}
