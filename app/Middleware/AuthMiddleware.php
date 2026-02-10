<?php
namespace App\Middleware;

use App\Core\Auth;
use App\Core\Helpers;

class AuthMiddleware
{
    public function handle()
    {
        if (!Auth::check()) {
            Helpers::redirect('/login');
        }
        $user = Auth::user();
        if ($user && ($user['status'] ?? 'active') !== 'active') {
            Auth::logout();
            Helpers::redirect('/login?suspended=1');
        }
    }
}

