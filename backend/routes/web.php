<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'PropertyLease Portal API', 'version' => '1.0']);
});
