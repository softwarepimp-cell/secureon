<?php
namespace App\Core;

class Controller
{
    public function view($view, $data = [])
    {
        $viewObj = new View();
        $viewObj->render($view, $data);
    }

    public function json($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}

