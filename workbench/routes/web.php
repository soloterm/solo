<?php

/**
 * @author Aaron Francis <aaron@tryhardstudios.com>
 *
 * @link https://aaronfrancis.com
 * @link https://x.com/aarondfrancis
 */

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
