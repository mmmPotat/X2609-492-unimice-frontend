<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\UniMice\Dto\MeDto;
use App\Services\UniMice\Endpoints\UsersEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Support\Enums\FormMethod;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Card;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Box;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Flex;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Modal;
use MoonShine\UI\Fields\Text;

/**
 * Профиль текущего пользователя — GET /users/me.
 *
 * Одна карточка «Данные пользователя», как в оригинале на Appsmith:
 * сетка «подпись — значение» (ФИО, контакты, компания) и прикреплённые
 * клиенты (GET /users/{id}/clients). Редактирование (PATCH /users через
 * ProfileUpdateController) открывается в модальном окне.
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

        $components[] = Flex::make([
            Heading::make('Данные пользователя', 4),
            Modal::make(
                title: 'Редактирование профиля',
                outer: ActionButton::make('Редактировать')->class('btn-primary'),
                components: [$this->editForm($me)],
            )
                ->name('profile-edit')
                ->wide()
                // Ошибки валидации после неудачного сохранения — открыть модалку повторно
                ->open(session()->has('errors')),
        ], justifyAlign: 'between');

        $components[] = Box::make([
            Grid::make($this->infoCells($me)),
            Heading::make('Прикреплённые клиенты', 5)->class('unimice-profile-clients-title'),
            ...$this->clientsComponents($me->id),
        ]);

        return $components;
    }

    private function editForm(MeDto $me): FormBuilder
    {
        return FormBuilder::make()
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
    }

    /**
     * Сетка «подпись — значение»: три колонки, как в оригинале.
     *
     * @return list<ComponentContract>
     */
    private function infoCells(MeDto $me): array
    {
        $fields = [
            ['Фамилия', $me->lastName],
            ['Имя', $me->firstName],
            ['Отчество', $me->middleName],
            ['Почта', $me->email],
            ['Телефон', $me->phone],
            ['Должность', $me->post],
            ['Юридическое лицо', $me->clientName],
            ['Группа', $me->group?->name],
            ['Руководитель', $me->chiefName],
        ];

        $cells = [];

        foreach ($fields as [$label, $value]) {
            $cells[] = Column::make([
                Heading::make($label, 6)->class('unimice-profile-label'),
                Heading::make($value ?: '—', 5)->class('unimice-profile-value'),
            ], colSpan: 4);
        }

        return $cells;
    }

    /**
     * Прикреплённые клиенты; сбой запроса не должен ронять всю страницу.
     *
     * @return list<ComponentContract>
     */
    private function clientsComponents(int $userId): array
    {
        try {
            $clients = app(UsersEndpoint::class)->clients($userId);
        } catch (UniMiceApiException $exception) {
            return [
                Alert::make(type: 'error')
                    ->content('Не удалось загрузить клиентов: '.$exception->getMessage()),
            ];
        }

        if ($clients === []) {
            return [Alert::make(type: 'info')->content('Клиенты не прикреплены.')];
        }

        $components = [];

        foreach ($clients as $client) {
            $components[] = Card::make($client->name);
        }

        return $components;
    }
}
