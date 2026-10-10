<?php

use App\Http\Controllers\Api\MetricsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'PropertyLease Portal API', 'version' => '1.0']);
});

Route::get('/up', function () {
    return response()->json(['status' => 'ok']);
});

Route::get('/metrics', MetricsController::class);
