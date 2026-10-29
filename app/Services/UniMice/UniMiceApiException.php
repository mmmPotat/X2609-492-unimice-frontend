<?php

declare(strict_types=1);

namespace App\Services\UniMice;

use RuntimeException;

/**
 * Ошибка API UniMICE. Для 422 carries ошибки валидации по полям —
 * MoonShine сможет подсветить их на форме.
 */
final class UniMiceApiException extends RuntimeException
{
    /**
     * @param array<string, list<string>>|null $errors ошибки валидации: поле => [сообщения]
     */
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly ?array $errors = null,
        public readonly ?string $apiMessage = null,
    ) {
        parent::__construct($message, $httpStatus);
    }

    /**
     * Ошибки одним плоским списком: ["title — обязательное поле", ...].
     *
     * @return list<string>
     */
    public function flattenedErrors(): array
    {
        if ($this->errors === null) {
            return [];
        }

        $flat = [];

        foreach ($this->errors as $field => $messages) {
            foreach ($messages as $message) {
                $flat[] = $field.' — '.$message;
            }
        }

        return $flat;
    }
}
