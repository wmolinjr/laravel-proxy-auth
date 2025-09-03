<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\OpenIdConnectController;

// Home route - redirects to login for guests, dashboard for authenticated users
Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
})->name('home');

// OpenID Connect Discovery endpoint - handled in oauth.php

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/oauth.php';
require __DIR__.'/admin.php';
