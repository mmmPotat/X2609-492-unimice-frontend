<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\MoonShine\Pages\EventCreatePage;
use App\MoonShine\Pages\EventDetailPage;
use App\Services\UniMice\Endpoints\EventsEndpoint;
use App\Services\UniMice\Endpoints\UsersEndpoint;
use App\Services\UniMice\UniMiceApiException;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Обработка формы создания заявки (мероприятие / орг. взнос).
 *
 * Город приходит выбранным из async-select (поиск городов —
 * CitySearchController), здесь только валидация и POST в API.
 */
final class EventCreateController extends Controller
{
    /** Статус «Новая заявка» — так заявка сразу попадает на рассмотрение. */
    private const STATUS_NEW = 2;

    /** Категории из справочника: event / arrangement_fee. */
    private const CATEGORY_EVENT_ID = 3;

    private const CATEGORY_FEE_ID = 6;

    /** Сообщения валидации — на русском (см. AGENTS.md). */
    private const VALIDATION_MESSAGES = [
        'title.required' => 'Укажите название заявки.',
        'title.max' => 'Название не должно быть длиннее 255 символов.',
        'typeId.required' => 'Выберите тип мероприятия.',
        'participantsCount.required' => 'Укажите количество участников.',
        'participantsCount.min' => 'Количество участников — не менее 1.',
        'startDate.required' => 'Укажите дату начала.',
        'startDate.date_format' => 'Дата начала должна быть в формате ГГГГ-ММ-ДД.',
        'finishDate.required' => 'Укажите дату завершения.',
        'finishDate.date_format' => 'Дата завершения должна быть в формате ГГГГ-ММ-ДД.',
        'finishDate.after_or_equal' => 'Дата завершения не может быть раньше даты начала.',
        'cityId.required' => 'Выберите город.',
        'description.max' => 'Комментарий не должен быть длиннее 5000 символов.',
    ];

    public function __construct(
        private readonly EventsEndpoint $events,
        private readonly UsersEndpoint $users,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $isFee = $request->query('category') === 'fee';

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'typeId' => [$isFee ? 'nullable' : 'required', 'integer', 'min:1'],
            'participantsCount' => ['required', 'integer', 'min:1'],
            'startDate' => ['required', 'date_format:Y-m-d'],
            'finishDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:startDate'],
            'cityId' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:5000'],
        ], self::VALIDATION_MESSAGES);

        return $this->createEvent($request, $validated, $isFee);
    }

    /**
     * Город выбран — создаём заявку через API.
     *
     * @param  array<string, mixed>  $validated
     */
    private function createEvent(Request $request, array $validated, bool $isFee): RedirectResponse
    {
        try {
            $me = $this->users->me();

            $event = $this->events->create([
                'title' => $validated['title'],
                'statusId' => self::STATUS_NEW,
                'typeId' => $isFee ? EventCreatePage::feeTypeId() : (int) $validated['typeId'],
                'categoryId' => $isFee ? self::CATEGORY_FEE_ID : self::CATEGORY_EVENT_ID,
                'cityId' => (int) $validated['cityId'],
                'participantsCount' => (int) $validated['participantsCount'],
                'startDate' => Carbon::parse($validated['startDate'])->format('Y-m-d'),
                'finishDate' => Carbon::parse($validated['finishDate'])->format('Y-m-d'),
                'description' => $validated['description'] ?? '',
                'clientInitiatorId' => $me->id,
                'agencyLegalEntityId' => $me->legalEntityId,
            ]);
        } catch (UniMiceApiException $exception) {
            if ($exception->httpStatus === 422 && $exception->errors !== null) {
                throw ValidationException::withMessages(
                    $this->mapApiErrors($exception->errors),
                );
            }

            return back()->withInput()->with('apiError', $exception->getMessage());
        }

        return redirect('/admin/page/'.EventDetailPage::URI.'?id='.$event->id)
            ->with('successMessage', 'Заявка «'.$event->title.'» создана');
    }

    /**
     * API говорит об ошибках на английском и в «своих» полях
     * (например, startDate вместо startDate API-формата) — мапим
     * на понятные русские подписи и поля формы.
     *
     * @param  array<string, list<string>>  $errors
     * @return array<string, string>
     */
    private function mapApiErrors(array $errors): array
    {
        $fieldMap = [
            'title' => 'Название',
            'typeId' => 'Тип мероприятия',
            'cityId' => 'Город',
            'participantsCount' => 'Количество участников',
            'startDate' => 'Начало',
            'finishDate' => 'Завершение',
            'statusId' => 'Статус',
            'categoryId' => 'Категория',
            'clientInitiatorId' => 'Инициатор',
            'agencyLegalEntityId' => 'Организация',
        ];

        $messages = [];

        foreach ($errors as $field => $fieldErrors) {
            $label = $fieldMap[$field] ?? $field;

            foreach ($fieldErrors as $error) {
                $messages[$field][] = $label.': '.$error;
            }
        }

        return $messages;
    }
}
