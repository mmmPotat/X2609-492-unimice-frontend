<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Pages\CompanyPage;
use App\MoonShine\Pages\EmployeesPage;
use App\MoonShine\Pages\EventCreatePage;
use App\MoonShine\Pages\EventDetailPage;
use App\MoonShine\Pages\EventsPage;
use App\MoonShine\Pages\ProfilePage;
use App\MoonShine\Pages\VenuesPage;
use Illuminate\Support\ServiceProvider;
use MoonShine\Contracts\Core\DependencyInjection\CoreContract;
use MoonShine\Laravel\DependencyInjection\MoonShine;
use MoonShine\Laravel\DependencyInjection\MoonShineConfigurator;

class MoonShineServiceProvider extends ServiceProvider
{
    /**
     * @param  CoreContract<MoonShineConfigurator>  $core
     */
    public function boot(CoreContract $core): void
    {
        $core
            ->resources([
                // Ресурсы админ-панели регистрируются здесь
            ])
            ->pages([
                EventsPage::class,
                EventDetailPage::class,
                EventCreatePage::class,
                EmployeesPage::class,
                ProfilePage::class,
                CompanyPage::class,
                VenuesPage::class,
                ...$core->getConfig()->getPages(),
            ])
        ;
    }
}
