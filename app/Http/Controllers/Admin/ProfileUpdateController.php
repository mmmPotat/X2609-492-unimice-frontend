<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\MoonShine\Pages\ProfilePage;
use App\Services\UniMice\Endpoints\UsersEndpoint;
use App\Services\UniMice\UniMiceApiException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Сохранение профиля текущего пользователя (PATCH /users).
 *
 * Имена полей формы совпадают с полями API — ошибки 422
 * показываются под соответствующими полями без маппинга.
 */
final class ProfileUpdateController extends Controller
{
    /** Сообщения валидации — на русском (см. AGENTS.md). */
    private const VALIDATION_MESSAGES = [
        'firstName.required' => 'Укажите имя.',
        'lastName.required' => 'Укажите фамилию.',
        'middleName.max' => 'Отчество не должно быть длиннее 100 символов.',
        'post.max' => 'Должность не должна быть длиннее 255 символов.',
        'phone.max' => 'Телефон не должен быть длиннее 30 символов.',
    ];

    public function __construct(
        private readonly UsersEndpoint $users,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'firstName' => ['required', 'string', 'max:100'],
            'lastName' => ['required', 'string', 'max:100'],
            'middleName' => ['nullable', 'string', 'max:100'],
            'post' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ], self::VALIDATION_MESSAGES);

        try {
            $this->users->update([
                'firstName' => $validated['firstName'],
                'lastName' => $validated['lastName'],
                'middleName' => $validated['middleName'] ?? null,
                'post' => $validated['post'] ?? null,
                'phone' => $validated['phone'] ?? null,
            ]);
        } catch (UniMiceApiException $exception) {
            if ($exception->httpStatus === 422 && $exception->errors !== null) {
                throw ValidationException::withMessages($exception->errors);
            }

            return back()->withInput()->with('apiError', $exception->getMessage());
        }

        return redirect('/admin/page/'.ProfilePage::URI)
            ->with('successMessage', 'Профиль обновлён');
    }
}
