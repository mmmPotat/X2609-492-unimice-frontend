<?php

declare(strict_types=1);

namespace Tests\Feature\MoonShine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

final class VenuesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'unimice.base_url' => 'https://api.unimice.test',
            'unimice.auth.email' => 'bot@example.com',
            'unimice.auth.password' => 'secret',
        ]);
    }

    public function test_venues_page_renders_cards_and_filter_form(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/suppliers/types' => Http::response([
                'status' => 'success',
                'data' => ['items' => [
                    ['id' => 1, 'name' => 'Отель', 'nameEn' => 'Hotel', 'description' => null],
                    ['id' => 11, 'name' => 'Арт-пространство', 'nameEn' => 'Art Space', 'description' => null],
                    ['id' => 15, 'name' => 'Билеты', 'nameEn' => 'Tickets', 'description' => null],
                ]],
            ]),
            '*/suppliers*' => Http::response([
                'status' => 'success',
                'data' => [
                    'items' => [$this->fakeSupplier()],
                    'total' => 1,
                ],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/venues');

        $response->assertOk();
        $response->assertSee('Каталог площадок (1)');
        $response->assertSee('Гранд-отель «Урал»'); // карточка
        $response->assertSee('Екатеринбург'); // город в карточке
        $response->assertSee('Название'); // фильтр-форма
        $response->assertSee('Звёздность');
        $response->assertSee('Только избранные');
        $response->assertSee('На странице');
        $response->assertDontSee('Билеты'); // не-площадки не показываем
        $response->assertDontSee('Активен'); // статус заказчик убрать попросил

        // карточки в сетке (класс Card MoonShine)
        $response->assertSee('card card-category');
        $response->assertSee('col-span-4');

        // по умолчанию запрос уходит со всеми типами-площадками и limit 12
        Http::assertSent(static function ($request) {
            return ($request->data()['typeIds'] ?? null) === '[1,2,3,4,5,6,7,8,9,10,11,12,13,14]'
                && ($request->data()['limit'] ?? null) === 12;
        });
    }

    public function test_appsmith_style_filters_are_passed_to_api(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/suppliers/types' => Http::response([
                'status' => 'success',
                'data' => ['items' => [
                    ['id' => 1, 'name' => 'Отель', 'nameEn' => 'Hotel', 'description' => null],
                ]],
            ]),
            '*/suppliers*' => Http::response([
                'status' => 'success',
                'data' => ['items' => [], 'total' => 0],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/venues?'.http_build_query([
                'name' => 'отель',
                'address' => 'Ленина',
                'cityId' => 6,
                'type' => [1, 11],
                'stars' => [4, 5, 0],
                'priceMin' => 1000,
                'priceMax' => 5000,
                'sizeMin' => 10,
                'sizeMax' => 500,
                'capacityMin' => 20,
                'capacityMax' => 300,
                'isFavorite' => '1',
                'limit' => 48,
            ]));

        $response->assertOk();
        $response->assertSee('Ничего не найдено');

        Http::assertSent(static fn ($request) => ($request->data()['name'] ?? null) === 'отель'
            && ($request->data()['address'] ?? null) === 'Ленина'
            && ($request->data()['cityId'] ?? null) === 6
            && ($request->data()['typeIds'] ?? null) === '[1,11]'
            && ($request->data()['stars'] ?? null) === '4,5,0'
            && ($request->data()['priceMin'] ?? null) === 1000.0
            && ($request->data()['priceMax'] ?? null) === 5000.0
            && ($request->data()['sizeMin'] ?? null) === 10
            && ($request->data()['sizeMax'] ?? null) === 500
            && ($request->data()['capacityMin'] ?? null) === 20
            && ($request->data()['capacityMax'] ?? null) === 300
            && ($request->data()['isFavorite'] ?? null) === 'true'
            && ($request->data()['limit'] ?? null) === 48);
    }

    public function test_pagination_renders_links_and_keeps_filters(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/suppliers/types' => Http::response([
                'status' => 'success',
                'data' => ['items' => [
                    ['id' => 1, 'name' => 'Отель', 'nameEn' => 'Hotel', 'description' => null],
                ]],
            ]),
            '*/suppliers*' => Http::response([
                'status' => 'success',
                'data' => [
                    'items' => [$this->fakeSupplier()],
                    'total' => 60, // 5 страниц по 12
                ],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/venues?page=2&name=отель&limit=12');

        $response->assertOk();
        $response->assertSee('pagination');
        $response->assertSee('page=1');
        $response->assertSee('page=5');
        // фильтры и limit сохраняются в ссылках страниц
        $response->assertSee('name=%D0%BE%D1%82%D0%B5%D0%BB%D1%8C');
        $response->assertSee('limit=12');
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeSupplier(): array
    {
        return [
            'id' => 26,
            'name' => 'Гранд-отель «Урал»',
            'nameEn' => null,
            'description' => 'Пятизвёздочный отель в центре.',
            'city' => [
                'id' => 1,
                'name' => 'Екатеринбург',
                'nameEn' => 'Yekaterinburg',
                'country' => ['id' => 1, 'name' => 'Россия', 'nameEn' => 'Russia'],
                'timezone' => 'Asia/Yekaterinburg',
            ],
            'address' => 'ул. Ленина, 1',
            'type' => ['id' => 1, 'name' => 'Отель', 'nameEn' => 'Hotel', 'description' => null],
            'status' => ['id' => 5, 'name' => 'Активен', 'nameEn' => 'Active', 'description' => null],
            'stars' => 5,
            'photos' => null,
            'deletedAt' => null,
            'isFavorite' => false,
        ];
    }
}
