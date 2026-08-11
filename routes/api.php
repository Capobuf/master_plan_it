<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    $fragments = glob(__DIR__.'/api/v1/*.php') ?: [];
    sort($fragments, SORT_STRING);

    foreach ($fragments as $fragment) {
        require $fragment;
    }
});
