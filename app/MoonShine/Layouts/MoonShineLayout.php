<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\ColorManager\ColorManager;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\MenuManager\MenuGroup;
use MoonShine\MenuManager\MenuItem;
use MoonShine\AssetManager\InlineCss;
use App\MoonShine\Pages\CompanyPage;
use App\MoonShine\Pages\EmployeesPage;
use App\MoonShine\Pages\EventCreatePage;
use App\MoonShine\Pages\EventsPage;
use App\MoonShine\Pages\HelpPage;
use App\MoonShine\Pages\ProfilePage;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = PurplePalette::class;

    // Включаем верхнюю панель навигации
    protected bool $topBar = true;

    // Отключаем левый сайдбар
    protected bool $sidebar = false;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
            // Кастомные стили для топ-навигации
            InlineCss::make(<<<'CSS'
                /* Топ-бар: делаем его более заметным и удобным */
                .layout-topbar {
                    border-bottom: 1px solid var(--ms-border-color, #e5e7eb);
                    background: var(--ms-bg-color, #ffffff);
                }

                .layout-topbar .menu--horizontal {
                    gap: 0.5rem;
                }

                .layout-topbar .menu-item {
                    padding: 0.625rem 1rem;
                    border-radius: 0.5rem;
                    transition: background-color 0.15s ease, color 0.15s ease;
                }

                .layout-topbar .menu-item:hover {
                    background-color: var(--ms-primary-100, #ede9fe);
                    color: var(--ms-primary-700, #6d28d9);
                }

                .layout-topbar .menu-item.active {
                    background-color: var(--ms-primary-500, #8b5cf6);
                    color: white;
                }

                .layout-topbar .menu-item.active:hover {
                    background-color: var(--ms-primary-600, #7c3aed);
                    color: white;
                }

                /* Логотип в топ-баре */
                .layout-topbar .menu-logo {
                    padding: 0.5rem 1rem;
                }

                /* Действия справа (профиль, тема, уведомления) */
                .layout-topbar .menu-actions {
                    gap: 0.5rem;
                }

                /* На мобильных: бургер-меню */
                @media (max-width: 768px) {
                    .layout-topbar .menu--horizontal {
                        flex-wrap: wrap;
                    }
                }

                /* Основной контент - убираем отступ слева от сайдбара */
                .layout-main {
                    margin-left: 0 !important;
                }

                /* Страница без сайдбара */
                .layout-page {
                    padding: 1.5rem;
                }
            CSS),
        ];
    }

    protected function menu(): array
    {
        return [
            MenuItem::make(EventsPage::class, 'Заявки'),
            MenuItem::make(EventCreatePage::class, 'Новое мероприятие'),
            MenuItem::make(EventCreatePage::class, 'Новый орг. взнос')
                ->setUrl(url('/admin/page/'.EventCreatePage::URI.'?category=fee')),
            MenuItem::make(EmployeesPage::class, 'Сотрудники'),
            MenuItem::make(CompanyPage::class, 'О компании'),
            MenuItem::make(HelpPage::class, 'Помощь')->icon('question-mark-circle'),
            MenuItem::make(ProfilePage::class, 'Профиль'),
            ...parent::menu(),
        ];
    }

    /**
     * @param ColorManager $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
