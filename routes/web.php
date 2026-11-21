<?php

use App\Http\Controllers\Admin\CitySearchController;
use App\Http\Controllers\Admin\EventCreateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/admin/event-create', EventCreateController::class)
    ->middleware('moonshine')
    ->name('admin.event-create');

Route::get('/admin/event-create/cities', CitySearchController::class)
    ->middleware('moonshine')
    ->name('admin.event-create.cities');
