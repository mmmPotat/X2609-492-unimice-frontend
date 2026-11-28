<?php

use App\Http\Controllers\Admin\CitySearchController;
use App\Http\Controllers\Admin\EventCreateController;
use App\Http\Controllers\Admin\ProfileUpdateController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::post('/admin/event-create', EventCreateController::class)
    ->middleware('moonshine')
    ->name('admin.event-create');

Route::get('/admin/event-create/cities', CitySearchController::class)
    ->middleware('moonshine')
    ->name('admin.event-create.cities');

Route::post('/admin/profile-update', ProfileUpdateController::class)
    ->middleware('moonshine')
    ->name('admin.profile-update');
