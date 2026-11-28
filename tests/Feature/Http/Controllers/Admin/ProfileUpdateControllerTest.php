<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use MoonShine\Laravel\Models\MoonshineUser;
use Tests\TestCase;

final class ProfileUpdateControllerTest extends TestCase
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

    public function test_update_sends_patch_and_redirects_to_profile(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users' => Http::response([
                'status' => 'success',
                'data' => ['user' => ['id' => 690, 'firstName' => 'Иван', 'lastName' => 'Петров']],
            ]),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.profile-update'), [
                'firstName' => 'Иван',
                'lastName' => 'Петров',
                'middleName' => 'Сидорович',
                'post' => 'Менеджер',
                'phone' => '89990001122',
            ]);

        $response->assertRedirect('/admin/page/profile');
        $response->assertSessionHas('successMessage');

        Http::assertSent(static fn ($request) => $request->method() === 'PATCH'
            && str_contains((string) $request->url(), '/users')
            && ($request['firstName'] ?? null) === 'Иван'
            && ($request['lastName'] ?? null) === 'Петров'
            && ($request['middleName'] ?? null) === 'Сидорович'
            && ($request['phone'] ?? null) === '89990001122');
    }

    public function test_required_fields_are_validated(): void
    {
        Http::fake(['*/auth/login' => Http::response(['token' => 'jwt-abc'])]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.profile-update'), ['firstName' => '', 'lastName' => '']);

        $response->assertSessionHasErrors(['firstName', 'lastName']);
        Http::assertNothingSent();
    }

    public function test_api_failure_shows_alert_and_keeps_input(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users' => Http::response([
                'status' => 'error',
                'data' => null,
                'message' => 'Internal server error',
                'errors' => null,
            ], 500),
        ]);

        $response = $this
            ->actingAs(MoonshineUser::factory()->create(), 'moonshine')
            ->post(route('admin.profile-update'), [
                'firstName' => 'Иван',
                'lastName' => 'Петров',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('apiError');
    }
}
