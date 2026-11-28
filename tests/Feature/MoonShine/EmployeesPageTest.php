<?php

declare(strict_types=1);

namespace Tests\Feature\MoonShine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

final class EmployeesPageTest extends TestCase
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

    public function test_employees_page_renders_table_with_search(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users*' => Http::response([
                'status' => 'success',
                'data' => [
                    'items' => [
                        $this->fakeUser(1, 'Иванова Анна Петровна', chief: 'Петров Пётр'),
                        $this->fakeUser(2, 'Сидоров Иван', group: null),
                    ],
                    'total' => 2,
                ],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/employees');

        $response->assertOk();
        $response->assertSee('Сотрудники (2)');
        $response->assertSee('Иванова Анна Петровна');
        $response->assertSee('Аналитик');
        $response->assertSee('Петров Пётр'); // ФИО из вложенного chief
        $response->assertSee('Найти');
    }

    public function test_search_query_is_passed_to_api(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users*' => Http::response([
                'status' => 'success',
                'data' => ['items' => [], 'total' => 0],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/employees?query=Иванова');

        $response->assertOk();
        $response->assertSee('Никого не найдено');

        Http::assertSent(static fn ($request) => ($request->data()['query'] ?? null) === 'Иванова');
    }

    public function test_page_and_limit_are_passed_to_api(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users*' => Http::response([
                'status' => 'success',
                'data' => ['items' => [$this->fakeUser(1, 'Иванова Анна Петровна')], 'total' => 1],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/employees');

        $response->assertOk();

        Http::assertSent(static fn ($request) => ($request->data()['page'] ?? null) === 1
            && ($request->data()['limit'] ?? null) === 25);
    }

    public function test_second_page_renders_and_keeps_search_query(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users*' => Http::response([
                'status' => 'success',
                'data' => [
                    'items' => [$this->fakeUser(26, 'Петров Пётр Пётрович')],
                    'total' => 30, // 25 на первой странице, одна запись на второй
                ],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/employees?query=Иванова&page=2');

        $response->assertOk();
        $response->assertSee('Петров Пётр Пётрович');
        $response->assertSee('Сотрудники (30)');
        // ссылки пагинации сохраняют и строку поиска, и номер соседней страницы
        $response->assertSee('page=1');
        $response->assertSee('query=');
    }

    public function test_profile_page_renders_user_cards(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => 690,
                        'email' => 'agent@test.com',
                        'fullName' => 'Агент Студентов Тестовый',
                        'shortName' => 'Агент Студентов',
                        'post' => 'Студент ФИИТ',
                        'phone' => '89998887766',
                        'createdAt' => '2026-10-03',
                        'clientId' => 5,
                        'role' => ['id' => 2, 'name' => 'Менеджер', 'nameEn' => 'Manager'],
                        'group' => ['id' => 20, 'name' => 'Travel менеджеры', 'nameEn' => null],
                        'client' => ['id' => 5, 'name' => 'ООО «Тест»', 'nameEn' => null],
                        'chief' => ['id' => 2, 'fullName' => 'Мельников Михей Денисович'],
                        'legalEntity' => ['id' => 5],
                    ],
                ],
            ]),
            '*/users/*/clients' => Http::response([
                'status' => 'success',
                'data' => [
                    'items' => [
                        [
                            'id' => 5,
                            'name' => 'ООО «Тест»',
                            'nameEn' => null,
                            'type' => 'agency',
                            'typeLabel' => 'Агентство',
                            'requestEmail' => 'requests@test.com',
                            'createdAt' => '2026-01-01',
                        ],
                        [
                            'id' => 23,
                            'name' => 'Тестовый клиент',
                            'nameEn' => null,
                            'type' => 'client',
                            'typeLabel' => 'Клиент',
                            'requestEmail' => null,
                            'createdAt' => '2026-03-20',
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/profile');

        $response->assertOk();
        $response->assertSee('Агент Студентов Тестовый');
        $response->assertSee('agent@test.com');
        $response->assertSee('Мельников Михей Денисович');
        $response->assertSee('ООО «Тест»');
        $response->assertSee('Travel менеджеры');
        // прикреплённые клиенты
        $response->assertSee('Прикреплённые клиенты');
        $response->assertSee('Тестовый клиент');
        $response->assertSee('Агентство');
        $response->assertSee('requests@test.com');
        // форма редактирования заполнена текущими значениями
        $response->assertSee('Редактирование профиля');
        $response->assertSee('Сохранить');
    }

    public function test_company_page_renders_client_and_legal_entities(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/clients' => Http::response([
                'status' => 'success',
                'data' => [
                    'id' => 5,
                    'name' => 'ООО «Интернейшнл Бизнес-Центр»',
                    'nameEn' => 'IBC Corporate Travel',
                    'type' => 'agency',
                    'typeLabel' => 'Агентство',
                    'requestEmail' => null,
                    'createdAt' => '2025-12-22 07:41:39',
                ],
            ]),
            '*/client/5/legal-entities/' => Http::response([
                'status' => 'success',
                'data' => [
                    'items' => [
                        [
                            'id' => 5,
                            'name' => 'ООО «ИБЦ»',
                            'nameEn' => null,
                            'clientId' => 5,
                            'inn' => '6661060352',
                            'kpp' => '667101001',
                            'vatRate' => '22',
                            'createdAt' => '2025-12-22',
                            'extraFields' => [],
                            'supplier' => [
                                'address' => 'Татищева 20/2',
                                'city' => [
                                    'id' => 1,
                                    'name' => 'Екатеринбург',
                                    'nameEn' => 'Yekaterinburg',
                                    'country' => ['id' => 1, 'name' => 'Россия', 'nameEn' => 'Russia'],
                                    'timezone' => 'Asia/Yekaterinburg',
                                ],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->get('/admin/page/company');

        $response->assertOk();
        $response->assertSee('ООО «Интернейшнл Бизнес-Центр»');
        $response->assertSee('Агентство');
        $response->assertSee('6661060352');
        $response->assertSee('Екатеринбург Татищева 20/2');
    }

    /**
     * @return array<string, mixed>
     */
    private function fakeUser(int $id, string $fullName, ?string $chief = null, ?array $group = ['id' => 20, 'name' => 'Аналитики', 'nameEn' => null]): array
    {
        return [
            'id' => $id,
            'email' => "user{$id}@test.com",
            'firstName' => 'Анна',
            'lastName' => 'Иванова',
            'middleName' => 'Петровна',
            'fullName' => $fullName,
            'shortName' => 'Иванова Анна',
            'post' => 'Аналитик',
            'phone' => '89998887766',
            'createdAt' => '2026-03-20',
            'role' => ['id' => 2, 'name' => 'Менеджер', 'nameEn' => 'Manager'],
            'group' => $group,
            'client' => ['id' => 5, 'name' => 'ООО «Тест»', 'nameEn' => null],
            'chief' => $chief === null ? null : ['id' => 99, 'fullName' => $chief],
            'photos' => null,
        ];
    }
}
