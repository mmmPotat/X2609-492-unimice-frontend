<?php

declare(strict_types=1);

namespace App\MoonShine\Pages;

use App\Services\UniMice\Dto\ClientLegalEntityDto;
use App\Services\UniMice\Endpoints\ClientsEndpoint;
use App\Services\UniMice\UniMiceApiException;
use MoonShine\Contracts\UI\ComponentContract;
use MoonShine\Laravel\Pages\Page;
use MoonShine\UI\Components\Alert;
use MoonShine\UI\Components\Card;
use MoonShine\UI\Components\Heading;
use MoonShine\UI\Components\Table\TableBuilder;
use MoonShine\UI\Fields\Text;
use Spatie\LaravelData\DataCollection;

/**
 * О компании — карточка клиента-агентства (GET /clients) и его
 * юридические лица с реквизитами (GET /client/{id}/legal-entities/).
 */
final class CompanyPage extends Page
{
    public const URI = 'company';

    public function getUriKey(): string
    {
        return self::URI;
    }

    public function getTitle(): string
    {
        return 'О компании';
    }

    /**
     * @return list<ComponentContract>
     */
    protected function components(): iterable
    {
        try {
            $client = app(ClientsEndpoint::class)->current();
            /** @var DataCollection<int, ClientLegalEntityDto> $legalEntities */
            $legalEntities = app(ClientsEndpoint::class)->legalEntities($client->id);
        } catch (UniMiceApiException $exception) {
            return [
                Alert::make(type: 'error')->content($exception->getMessage()),
            ];
        }

        $rows = [];

        foreach ($legalEntities as $entity) {
            $rows[] = [
                'name' => $entity->name,
                'inn' => $entity->inn ?? '—',
                'kpp' => $entity->kpp ?? '—',
                'address' => trim(($entity->city?->name ?? '').' '.($entity->address ?? '')) ?: '—',
                'vatRate' => $entity->vatRate !== null ? $entity->vatRate.'%' : '—',
            ];
        }

        return [
            Heading::make($client->name),
            Card::make('Компания', values: [
                'Тип' => $client->typeLabel ?? $client->type ?? '—',
                'Email для заявок' => $client->requestEmail ?? '—',
                'Клиент с' => $client->createdAt ?? '—',
            ]),

            TableBuilder::make()
                ->items($rows)
                ->fields([
                    Text::make('Юрлицо', 'name'),
                    Text::make('ИНН', 'inn'),
                    Text::make('КПП', 'kpp'),
                    Text::make('Адрес', 'address'),
                    Text::make('НДС', 'vatRate'),
                ]),
        ];
    }
}
