<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'name'    => config('app.name'),
        'type'    => 'REST API',
        'version' => 'v1',
        'base'    => url('/api/v1'),
        'health'  => url('/up'),
        'docs'    => url('/docs/api'),
    ]);
});
