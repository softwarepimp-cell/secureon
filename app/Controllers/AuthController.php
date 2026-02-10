<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\CSRF;
use App\Core\Helpers;
use App\Core\RateLimiter;
use App\Core\Auth;
use App\Models\User;
use App\Models\AuditLog;

class AuthController extends Controller
{
    public function login()
    {
        $this->view('auth/login');
    }

    public function doLogin()
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/login');
        }
        $key = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'ip');
        if (!RateLimiter::check($key, 5, 300)) {
            $this->view('auth/login', ['error' => 'Too many attempts. Try again in 5 minutes.']);
            return;
        }
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $user = User::findByEmail($email);
        if ($user && ($user['status'] ?? 'active') !== 'active') {
            $this->view('auth/login', ['error' => 'Account suspended. Contact support.']);
            return;
        }
        if (Auth::attempt($email, $password)) {
            $user = Auth::user();
            AuditLog::log('login', $user['id'], null, []);
            Helpers::redirect('/dashboard');
        }
        $this->view('auth/login', ['error' => 'Invalid credentials']);
    }

    public function register()
    {
        $this->view('auth/register');
    }

    public function doRegister()
    {
        if (!CSRF::validate($_POST['_csrf'] ?? '')) {
            Helpers::redirect('/register');
        }
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (!$name || !$email || !$password) {
            $this->view('auth/register', ['error' => 'All fields are required.']);
            return;
        }
        if (User::findByEmail($email)) {
            $this->view('auth/register', ['error' => 'Email already exists.']);
            return;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $userId = User::create($name, $email, $hash);
        Auth::login($userId);
        AuditLog::log('register', $userId, null, []);
        Helpers::redirect('/dashboard');
    }

    public function logout()
    {
        Auth::logout();
        $basePath = rtrim((string)(parse_url((string)Helpers::config('BASE_URL', ''), PHP_URL_PATH) ?? ''), '/');
        $target = str_ends_with($basePath, '/public') ? '/' : '/public';
        Helpers::redirect($target);
    }

    public function forgot()
    {
        $this->view('auth/forgot');
    }

    public function doForgot()
    {
        $this->view('auth/forgot', ['message' => 'If an account exists, a reset link has been sent (stub).']);
    }

    public function verify()
    {
        $this->view('auth/verify');
    }
}

