<?php
namespace App\Core;

class View
{
    public function render($view, $data = [])
    {
        extract($data);
        $path = __DIR__ . '/../../views/' . $view . '.php';
        if (!file_exists($path)) {
            echo 'View not found.';
            return;
        }
        include $path;
    }
}

