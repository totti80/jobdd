<?php

use App\Http\Controllers\IconController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserQueryController;

Route::get('/query', [UserQueryController::class, 'create'])->name('query.create');
Route::post('/query', [UserQueryController::class, 'store'])->name('query.store');

Route::get('/results/{userQuery}', [UserQueryController::class, 'results'])
    ->name('query.results');

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/hello', function () {
    return view('hello');
});

Route::get('/hello/{name}', function (string $name) {
    return "こんにちは、{$name}さん！";
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [IconController::class, 'index'])
        ->name('dashboard');

    Route::get('/icon/{id}/edit', [IconController::class, 'edit'])
        ->name('icon.edit');

    Route::get('/icon/create', [IconController::class, 'create'])
        ->name('icon.create');

    Route::post('/icon/store', [IconController::class, 'store'])
        ->name('icon.store');

    Route::delete('/icon/{id}', [IconController::class, 'destroy'])
        ->name('icon.destroy');

    Route::patch('/icon/{id}', [IconController::class, 'update'])
        ->name('icon.update');
});

require __DIR__ . '/settings.php';
