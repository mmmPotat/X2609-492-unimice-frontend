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
 * Город подбирается в два захода без JS: пока cityId не выбран,
 * контроллер вместо создания заявки ищет города по подстроке cityQuery
 * и возвращает форму со списком найденных вариантов.
 */
final class EventCreateController extends Controller
{
    /** Статус «Новая заявка» — так заявка сразу попадает на рассмотрение. */
    private const STATUS_NEW = 2;

    /** Категории из справочника: event / arrangement_fee. */
    private const CATEGORY_EVENT_ID = 3;

    private const CATEGORY_FEE_ID = 6;

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
            'startDate' => ['required', 'date'],
            'finishDate' => ['required', 'date', 'after:startDate'],
            'cityQuery' => ['nullable', 'string', 'min:2', 'max:255'],
            'cityId' => ['nullable', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        if (empty($validated['cityId'])) {
            return $this->pickCity($request, $validated);
        }

        return $this->createEvent($request, $validated, $isFee);
    }

    /**
     * Первый заход: ищем города по подстроке и возвращаем форму
     * со списком вариантов. Без подстроки — просим указать город.
     */
    private function pickCity(Request $request, array $validated): RedirectResponse
    {
        $query = trim((string) ($validated['cityQuery'] ?? ''));

        if ($query === '') {
            throw ValidationException::withMessages([
                'cityQuery' => 'Укажите город: введите название и нажмите «Создать заявку» для подбора.',
            ]);
        }

        try {
            $cities = $this->events->searchCities($query);
        } catch (UniMiceApiException $exception) {
            return back()->withInput()->with('apiError', $exception->getMessage());
        }

        $options = [];

        foreach ($cities as $city) {
            $options[$city->id] = $city->name;
        }

        if ($options === []) {
            throw ValidationException::withMessages([
                'cityQuery' => 'Город «'.$query.'» не найден. Попробуйте другой запрос.',
            ]);
        }

        return back()->withInput()->with('cityOptions', $options);
    }

    /**
     * Город выбран — создаём заявку через API.
     *
     * @param array<string, mixed> $validated
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
                'startDate' => Carbon::parse($validated['startDate'])->format('Y-m-d H:i:s'),
                'finishDate' => Carbon::parse($validated['finishDate'])->format('Y-m-d H:i:s'),
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
     * @param array<string, list<string>> $errors
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
