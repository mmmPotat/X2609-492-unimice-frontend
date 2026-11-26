<?php

declare(strict_types=1);

namespace App\MoonShine\Layouts;

use App\MoonShine\Pages\CompanyPage;
use App\MoonShine\Pages\EmployeesPage;
use App\MoonShine\Pages\EventCreatePage;
use App\MoonShine\Pages\EventsPage;
use App\MoonShine\Pages\ProfilePage;
use App\MoonShine\Pages\VenuesPage;
use MoonShine\AssetManager\Css;
use MoonShine\ColorManager\ColorManager;
use MoonShine\ColorManager\Palettes\PurplePalette;
use MoonShine\Contracts\ColorManager\ColorManagerContract;
use MoonShine\Contracts\ColorManager\PaletteContract;
use MoonShine\Laravel\Layouts\AppLayout;
use MoonShine\MenuManager\MenuItem;

final class MoonShineLayout extends AppLayout
{
    /**
     * @var null|class-string<PaletteContract>
     */
    protected ?string $palette = PurplePalette::class;

    protected function assets(): array
    {
        return [
            ...parent::assets(),
            Css::make(asset('css/unimice-admin.css')),
        ];
    }

    protected function menu(): array
    {
        return [
            MenuItem::make(EventsPage::class, 'Заявки'),
            MenuItem::make(EventCreatePage::class, 'Новое мероприятие'),
            MenuItem::make(EventCreatePage::class, 'Новый орг. взнос')
                ->setUrl(url('/admin/page/'.EventCreatePage::URI.'?category=fee')),
            MenuItem::make(VenuesPage::class, 'Каталог площадок'),
            MenuItem::make(EmployeesPage::class, 'Сотрудники'),
            MenuItem::make(CompanyPage::class, 'О компании'),
            MenuItem::make(ProfilePage::class, 'Профиль'),
            ...parent::menu(),
        ];
    }

    /**
     * @param  ColorManager  $colorManager
     */
    protected function colors(ColorManagerContract $colorManager): void
    {
        parent::colors($colorManager);

        // $colorManager->primary('#00000');
    }
}
