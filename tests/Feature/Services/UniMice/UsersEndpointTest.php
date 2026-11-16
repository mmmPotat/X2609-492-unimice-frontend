<?php

declare(strict_types=1);

namespace Tests\Feature\Services\UniMice;

use App\Services\UniMice\Dto\MeDto;
use App\Services\UniMice\Endpoints\UsersEndpoint;
use App\Services\UniMice\UniMiceClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class UsersEndpointTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'unimice.base_url' => 'https://api.unimice.test',
            'unimice.auth.email' => 'bot@example.com',
            'unimice.auth.password' => 'secret',
        ]);
    }

    public function test_me_reads_user_with_nested_legal_entity(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users/me' => Http::response([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'id' => 690,
                        'clientId' => 5,
                        'legalEntity' => ['id' => 5, 'name' => 'ООО «Ракета»'],
                    ],
                ],
            ]),
        ]);

        $me = (new UsersEndpoint(new UniMiceClient()))->me();

        $this->assertInstanceOf(MeDto::class, $me);
        $this->assertSame(690, $me->id);
        $this->assertSame(5, $me->clientId);
        $this->assertSame(5, $me->legalEntityId);
    }

    public function test_me_tolerates_missing_legal_entity(): void
    {
        Http::fake([
            '*/auth/login' => Http::response(['token' => 'jwt-abc']),
            '*/users/me' => Http::response([
                'status' => 'success',
                'data' => ['user' => ['id' => 1]],
            ]),
        ]);

        $me = (new UsersEndpoint(new UniMiceClient()))->me();

        $this->assertSame(1, $me->id);
        $this->assertNull($me->clientId);
        $this->assertNull($me->legalEntityId);
    }
}
