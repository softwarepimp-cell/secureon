<?php
namespace App\Middleware;

use App\Core\Auth;

class SuperAdminMiddleware
{
    public function handle()
    {
        $user = Auth::user();
        if (!$user || $user['role'] !== 'super_admin') {
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
    }
}
