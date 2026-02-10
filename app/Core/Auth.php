<?php
namespace App\Core;

use App\Models\User;

class Auth
{
    public static function user()
    {
        if (!empty($_SESSION['user_id'])) {
            return User::find($_SESSION['user_id']);
        }
        return null;
    }

    public static function check(): bool
    {
        return !empty($_SESSION['user_id']);
    }

    public static function attempt($email, $password): bool
    {
        $user = User::findByEmail($email);
        if ($user && password_verify($password, $user['password_hash'])) {
            self::login($user['id']);
            return true;
        }
        return false;
    }

    public static function login($userId)
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
    }

    public static function logout()
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }
}

