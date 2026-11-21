<?php

declare(strict_types=1);

namespace App\Services\UniMice\Dto;

use Spatie\LaravelData\Data;

/**
 * Клиент платформы (в нашем контексте — агентство, чья админка).
 * GET /clients отдаёт клиента текущего пользователя одним объектом.
 */
final class ClientDto extends Data
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly ?string $nameEn,
        public readonly ?string $type,
        public readonly ?string $typeLabel,
        public readonly ?string $requestEmail,
        public readonly ?string $createdAt,
    ) {}
}
