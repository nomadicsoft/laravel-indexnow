<?php

use Illuminate\Support\Facades\Route;
use NomadicSoft\LaravelIndexNow\Http\Controllers\KeyController;

Route::middleware((array) config('indexnow.route.middleware', []))
    ->get('/{indexNowKey}.txt', KeyController::class)
    ->where('indexNowKey', '[A-Za-z0-9-]{8,128}')
    ->name((string) config('indexnow.route.name', 'indexnow.key'));
