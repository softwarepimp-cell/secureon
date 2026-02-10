<?php
namespace App\Middleware;

use App\Core\Auth;

class AdminMiddleware
{
    public function handle()
    {
        $user = Auth::user();
        if (!$user || $user['role'] !== 'admin') {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}

