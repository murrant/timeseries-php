<?php

use Illuminate\Support\Facades\Route;

Route::prefix('data')->name('data.')->group(function () {
    Route::resource('graph', \App\Http\Controllers\GraphController::class)->only(['index', 'show']);
});
