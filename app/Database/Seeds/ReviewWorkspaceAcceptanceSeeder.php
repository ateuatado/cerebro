<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Controllers\DocumentReviewController;

class ReviewWorkspaceAcceptanceSeeder extends Seeder
{
    public function run()
    {
        echo "\n=== TESTANDO CONTROLLER extractRegion(1, 5) ===\n";

        // Configurar POST global
        $_POST['x']        = 50;
        $_POST['y']        = 100;
        $_POST['width']    = 400;
        $_POST['height']   = 300;
        $_POST['canvas_w'] = 800;
        $_POST['canvas_h'] = 1000;

        // Injetar request mock
        $request = \Config\Services::request();
        $request->setGlobal('post', $_POST);

        $controller = new DocumentReviewController();
        $controller->initController($request, \Config\Services::response(), \Config\Services::logger());

        $response = $controller->extractRegion(1, 5);

        echo "HTTP Status: " . $response->getStatusCode() . "\n";
        echo "Body Output:\n" . $response->getBody() . "\n";
    }
}
