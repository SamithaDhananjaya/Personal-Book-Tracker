<?php

use App\Http\Controllers\PublicProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/readers/{profileSlug}', PublicProfileController::class)->name('readers.show');
