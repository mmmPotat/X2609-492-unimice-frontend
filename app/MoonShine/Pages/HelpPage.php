<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Models\HelpRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\ActionButton;
use MoonShine\UI\Components\Card;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Layout\Column;
use MoonShine\UI\Components\Layout\Grid;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\ID;
use MoonShine\UI\Fields\Select;
use MoonShine\UI\Fields\Text;
use MoonShine\UI\Fields\Textarea;
use MoonShine\UI\Fields\Badge;

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

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        $user = Auth::user();

        // Обработка отправки формы
        if (request()->isMethod('post') && request()->has('help_submit')) {
            $validated = request()->validate([
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:5000',
                'category' => 'nullable|string|max:50',
                'priority' => 'required|in:low,normal,high,urgent',
            ]);

            HelpRequest::create([
                'user_id' => $user->id,
                'subject' => $validated['subject'],
                'message' => $validated['message'],
                'category' => $validated['category'] ?? 'general',
                'priority' => $validated['priority'],
                'status' => 'new',
            ]);

            return [
                Alert::make(type: 'success')
                    ->content('Ваше обращение успешно отправлено! Мы свяжемся с вами в ближайшее время.'),
            ];
        }

        // Получаем обращения пользователя
        $requests = HelpRequest::where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return [
            Heading::make('Помощь и поддержка')->class('mb-6'),
// Информационная карточка
            Card::make('Как мы можем помочь?', values: [
                'Заполните форму ниже, чтобы создать обращение в службу поддержки.' => null,
                'Мы ответим в течение рабочего дня.' => null,
                'Для срочных вопросов звоните: +7 (XXX) XXX-XX-XX' => null,
            ])->class('mb-6'),

            // Форма создания обращения
            Card::make('Новое обращение', [
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

                ActionButton::make('Отправить обращение')
                    ->customAttributes([
                        'type' => 'submit',
                        'name' => 'help_submit',
                    ])
                    ->class('mt-4 btn-primary'),
            ])->class('mb-6'),

            // История обращений
            Heading::make('История ваших обращений')->class('mb-4'),

            $requests->isEmpty()
                ? Alert::make(type: 'info')->content('У вас пока нет обращений в поддержку.')
                : TableBuilder::make()
                    ->items($requests)
                    ->fields([
                        ID::make()->sortable(),
                        Text::make('Тема', 'subject')->sortable(),
                        Badge::make('Статус', 'status')
                            ->colors([
                                'new' => 'info',
                                'in_progress' => 'warning',
                                'resolved' => 'success',
                                'closed' => 'gray',
                            ])
                            ->labels([
                                'new' => 'Новая',
                                'in_progress' => 'В работе',
                                'resolved' => 'Решена',
                                'closed' => 'Закрыта',
                            ]),
                        Badge::make('Приоритет', 'priority')
                            ->colors([
                                'low' => 'gray',
                                'normal' => 'info',
                                'high' => 'warning',
                                'urgent' => 'danger',
                            ])
                            ->labels([
                                'low' => 'Низкий',
                                'normal' => 'Обычный',
                                'high' => 'Высокий',
                                'urgent' => 'Срочный',
                            ]),
                        Text::make('Категория', 'category')
                            ->format(fn ($v) => match ($v) {
                                'technical' => 'Технические проблемы',
                                'billing' => 'Оплата и биллинг',
                                'account' => 'Аккаунт и доступы',
                                'feature' => 'Предложение функции',
                                'general' => 'Общий вопрос',
                                default => $v,
                            }),
                        Text::make('Дата', 'created_at')
                            ->format(fn ($v) => $v?->format('d.m.Y H:i')),
                    ])
                    ->paginate(10)
                    ->searchable(),
        ];
    }
}
