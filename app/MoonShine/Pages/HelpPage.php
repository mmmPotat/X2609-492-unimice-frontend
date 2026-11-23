<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\HelpRequest;
use Illuminate\Support\Facades\Auth;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\Laravel\TypeCasts\ModelCaster;
use MoonShine\Support\Enums\FormMethod;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Card;
use MoonShine\UI\Components\FormBuilder;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;

final class HelpPage extends Page
{
    public const URI = 'help';

    public function getUriKey(): string
    {
        return self::URI;
    }

    public function getTitle(): string
    {
        return 'Помощь и поддержка';
    }

    /** @return list<ComponentContract> */
    protected function components(): iterable
    {
        // Получаем текущего пользователя MoonShine
        $user = Auth::guard('moonshine')->user();

        if (!$user) {
            abort(403);
        }

        // Получаем обращения текущего пользователя
        $requests = HelpRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        $components = [
            Heading::make('Помощь и поддержка')
                ->class('mb-6'),

            // Информационная карточка
            Card::make('Как мы можем помочь?', values: [
                'Заполните форму ниже, чтобы создать обращение в службу поддержки.' => null,
                'Мы ответим в течение рабочего дня.' => null,
                'Для срочных вопросов звоните: +7 (XXX) XXX-XX-XX' => null,
            ])->class('mb-6'),

            // Форма создания обращения
            FormBuilder::make()
                ->action(route('admin.help-request.store'))
                ->method(FormMethod::POST)
                ->fields([
                    Grid::make([
                        Column::make([
                            Text::make('Тема', 'subject')
                                ->required()
                                ->placeholder('Кратко опишите суть вопроса'),
                        ])->columnSpan(12),

                        Column::make([
                            Select::make('Категория', 'category')
                                ->options([
                                    'technical' => 'Технические проблемы',
                                    'billing' => 'Оплата и биллинг',
                                    'account' => 'Аккаунт и доступы',
                                    'feature' => 'Предложение функции',
                                    'general' => 'Общий вопрос',
                                ])
                                ->default('general'),
                        ])->columnSpan(6),

                        Column::make([
                            Select::make('Приоритет', 'priority')
                                ->options([
                                    'low' => 'Низкий',
                                    'normal' => 'Обычный',
                                    'high' => 'Высокий',
                                    'urgent' => 'Срочный',
                                ])
                                ->default('normal'),
                        ])->columnSpan(6),

                        Column::make([
                            Textarea::make('Сообщение', 'message')
                                ->required()
                                ->customAttributes(['rows' => 6])
                                ->placeholder('Опишите проблему подробно...'),
                        ])->columnSpan(12),
                    ]),
                ])
                ->submit('Отправить обращение')
                ->class('mb-6'),
        ];

        // Сообщение об успешном создании обращения
        if (session()->has('successMessage')) {
            array_unshift(
                $components,
                Alert::make(type: 'success')
                    ->content(session('successMessage'))
            );
        }

        // История обращений
        $components[] = Heading::make('История ваших обращений')
            ->class('mb-4');

        if ($requests->isEmpty()) {
            $components[] = Alert::make(type: 'info')
                ->content('У вас пока нет обращений в поддержку.');
        } else {
            $components[] = TableBuilder::make(items: $requests)
                ->fields([
                    ID::make(),

                    Text::make('Тема', 'subject'),

                    Text::make('Статус', 'status'),

                    Text::make('Приоритет', 'priority'),

                    Text::make('Категория', 'category'),

                    Text::make('Дата создания', 'created_at'),
                ])
                ->cast(new ModelCaster(HelpRequest::class))
                ->withNotFound();
        }

        return $components;
    }
}
