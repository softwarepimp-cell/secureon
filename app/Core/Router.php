<?php
namespace App\Core;

class Router
{
    private $routes = [];

    public function get($path, $handler, $middleware = [])
    {
        $this->add('GET', $path, $handler, $middleware);
    }

    public function post($path, $handler, $middleware = [])
    {
        $this->add('POST', $path, $handler, $middleware);
    }

    private function add($method, $path, $handler, $middleware)
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middleware');
    }

    public function dispatch()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        // Strip base path when app is in a subdirectory (e.g., /secure or /secure/public)
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
        if ($base && $base !== '/' && strpos($uri, $base) === 0) {
            $uri = substr($uri, strlen($base));
            if ($uri === '') {
                $uri = '/';
            }
        }

        // Also respect BASE_URL path if set (covers root rewrites to /secure)
        $config = require __DIR__ . '/config.php';
        $basePath = rtrim(parse_url($config['BASE_URL'] ?? '', PHP_URL_PATH) ?? '', '/');
        if ($basePath && $basePath !== '/' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath));
            if ($uri === '') {
                $uri = '/';
            }
        }

        foreach ($this->routes as $route) {
            if ($method !== $route['method']) {
                continue;
            }
            $pattern = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['path']);
            $pattern = '#^' . $pattern . '$#';
            if (preg_match($pattern, $uri, $matches)) {
                foreach ($route['middleware'] as $mw) {
                    $mw->handle();
                }
                [$controller, $action] = explode('@', $route['handler']);
                $class = 'App\\Controllers\\' . $controller;
                $instance = new $class();
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                call_user_func_array([$instance, $action], $params);
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }
}

