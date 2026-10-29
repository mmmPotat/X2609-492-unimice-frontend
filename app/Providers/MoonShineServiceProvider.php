<?php

declare(strict_types=1);

namespace App\Providers;

use App\MoonShine\Pages\EventsPage;
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
                ...$core->getConfig()->getPages(),
            ])
        ;
    }
}
