<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\UniMice\Endpoints\EventsEndpoint;
use App\Services\UniMice\UniMiceApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MoonShine\Support\DTOs\Select\Option;

/**
 * Поддержка async-поиска городов для Select в форме создания заявки.
 *
 * MoonShine select с ->async($url) шлёт GET на $url с параметром query
 * (подстрока поиска) и ждёт JSON [Option(label, value), ...].
 */
final class CitySearchController extends Controller
{
    public function __construct(
        private readonly EventsEndpoint $events,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('query', ''));

        if ($query === '') {
            return response()->json([]);
        }

        try {
            $cities = $this->events->searchCities($query);
        } catch (UniMiceApiException) {
            // выпадашка просто останется пустой — форма покажет ошибки
            // через обычный сценарий отправки
            return response()->json([]);
        }

        $options = [];

        foreach ($cities as $city) {
            $options[] = Option::make($city->name, (string) $city->id)->toArray();
        }

        return response()->json($options);
    }
}
