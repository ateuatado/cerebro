<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

// Auth — Spec 2
$routes->get('login', 'AuthController::loginForm');
$routes->post('login', 'AuthController::loginAction');
$routes->get('logout', 'AuthController::logout');
// Scaffolding de teste — será substituído pelo endpoint real de confirmação
$routes->post('teste-autorizacao-coordenador', 'AuthController::testeAutorizacao', ['filter' => 'auth']);
