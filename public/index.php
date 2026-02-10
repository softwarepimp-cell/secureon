<?php
require __DIR__ . '/../app/Core/config.php';
require __DIR__ . '/../app/Core/Autoload.php';

use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\AdminMiddleware;
use App\Middleware\SuperAdminMiddleware;

// Session hardening
session_name('secureon_session');
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
$cookieParams = session_get_cookie_params();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => $cookieParams['path'],
    'domain' => $cookieParams['domain'],
    'secure' => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

$router = new Router();

// Marketing
$router->get('/', 'HomeController@home');
$router->get('/pricing', 'HomeController@pricing');

// Auth
$router->get('/login', 'AuthController@login');
$router->post('/login', 'AuthController@doLogin');
$router->get('/register', 'AuthController@register');
$router->post('/register', 'AuthController@doRegister');
$router->post('/logout', 'AuthController@logout', [new AuthMiddleware()]);
$router->get('/forgot-password', 'AuthController@forgot');
$router->post('/forgot-password', 'AuthController@doForgot');
$router->get('/verify-email', 'AuthController@verify');

// App pages
$router->get('/dashboard', 'DashboardController@index', [new AuthMiddleware()]);
$router->get('/systems', 'SystemsController@index', [new AuthMiddleware()]);
$router->get('/systems/new', 'SystemsController@createForm', [new AuthMiddleware()]);
$router->post('/systems/new', 'SystemsController@create', [new AuthMiddleware()]);
$router->get('/systems/{id}', 'SystemsController@show', [new AuthMiddleware()]);
$router->post('/systems/{id}/trigger-config', 'SystemsController@updateTriggerConfig', [new AuthMiddleware()]);
$router->get('/systems/{id}/download-agent', 'SystemsController@downloadAgentBundle', [new AuthMiddleware()]);
$router->post('/systems/{id}/badge-token/create', 'SystemsController@createBadgeToken', [new AuthMiddleware()]);
$router->post('/systems/{id}/badge-token/revoke', 'SystemsController@revokeBadgeToken', [new AuthMiddleware()]);
$router->get('/systems/{id}/download-badge', 'SystemsController@downloadBadgeScript', [new AuthMiddleware()]);
$router->post('/systems/{id}/delete', 'SystemsController@delete', [new AuthMiddleware()]);
$router->get('/backups', 'BackupsController@index', [new AuthMiddleware()]);
$router->get('/backups/{id}/download-sql', 'BackupsController@downloadSql', [new AuthMiddleware()]);
$router->get('/alerts', 'DashboardController@alerts', [new AuthMiddleware()]);
$router->get('/settings', 'DashboardController@settings', [new AuthMiddleware()]);
$router->post('/settings/profile', 'DashboardController@updateProfile', [new AuthMiddleware()]);
$router->post('/settings/password', 'DashboardController@updatePassword', [new AuthMiddleware()]);
$router->get('/billing', 'BillingController@index', [new AuthMiddleware()]);
$router->post('/billing/estimate', 'BillingController@estimate', [new AuthMiddleware()]);
$router->post('/billing/request', 'BillingController@requestPayment', [new AuthMiddleware()]);
$router->get('/billing/requests', 'BillingController@requests', [new AuthMiddleware()]);
$router->get('/admin', 'DashboardController@admin', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->post('/admin/users/{id}/suspend', 'DashboardController@suspendUser', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->post('/admin/users/{id}/unsuspend', 'DashboardController@unsuspendUser', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->post('/admin/users/{id}/role', 'DashboardController@updateUserRole', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->post('/admin/users/{id}/plan', 'DashboardController@updateUserPlan', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->get('/admin/packages', 'AdminBillingController@packages', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->get('/admin/packages/new', 'AdminBillingController@packageNew', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->post('/admin/packages/create', 'AdminBillingController@packageCreate', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->get('/admin/packages/{id}/edit', 'AdminBillingController@packageEdit', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->post('/admin/packages/{id}/update', 'AdminBillingController@packageUpdate', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->post('/admin/packages/{id}/toggle', 'AdminBillingController@packageToggle', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->get('/admin/billing/requests', 'AdminBillingController@billingRequests', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->get('/admin/billing/requests/{id}', 'AdminBillingController@billingRequestDetail', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->post('/admin/billing/requests/{id}/approve', 'AdminBillingController@approveRequest', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->post('/admin/billing/requests/{id}/decline', 'AdminBillingController@declineRequest', [new AuthMiddleware(), new SuperAdminMiddleware()]);
$router->post('/admin/billing/users/{id}/adjust', 'AdminBillingController@adjustUserSubscription', [new AuthMiddleware(), new SuperAdminMiddleware()]);

// Download via signed token
$router->get('/download/{token}', 'BackupsController@downloadSigned');

// API (session-auth JSON)
$router->get('/api/v1/dashboard/metrics', 'ApiController@metrics', [new AuthMiddleware()]);
$router->get('/api/v1/dashboard/latest-events', 'ApiController@latestEvents', [new AuthMiddleware()]);
$router->get('/api/v1/systems/{id}/latest-status', 'ApiController@latestStatus', [new AuthMiddleware()]);
$router->post('/api/v1/systems/{id}/trigger-now', 'ApiController@triggerNow', [new AuthMiddleware()]);
$router->post('/api/v1/systems/{id}/test-trigger', 'ApiController@testTrigger', [new AuthMiddleware()]);
$router->post('/api/v1/systems/{id}/prepare-agent-bundle', 'ApiController@prepareAgentBundle', [new AuthMiddleware()]);
$router->post('/api/v1/systems/{id}/tokens', 'ApiController@createToken', [new AuthMiddleware()]);
$router->post('/api/v1/backups/{id}/sign-download', 'ApiController@signDownload', [new AuthMiddleware()]);
$router->post('/api/v1/backups/{id}/delete', 'ApiController@deleteBackup', [new AuthMiddleware()]);
$router->get('/api/v1/badge/status', 'BadgeApiController@status');

// Agent API (Bearer token)
$router->post('/api/v1/agent/handshake', 'AgentApiController@handshake');
$router->post('/api/v1/agent/backup/start', 'AgentApiController@start');
$router->post('/api/v1/agent/backup/progress', 'AgentApiController@progress');
$router->post('/api/v1/agent/backup/complete', 'AgentApiController@complete');
$router->post('/api/v1/agent/backup/fail', 'AgentApiController@fail');
$router->post('/api/v1/agent/backup/upload', 'AgentApiController@upload');
$router->get('/api/v1/agent/backup/restore/{backup_id}', 'AgentApiController@restoreDownload');

$router->dispatch();

