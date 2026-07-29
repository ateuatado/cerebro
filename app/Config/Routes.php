<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ─── Auth — Spec 2 ────────────────────────────────────────────────────
$routes->get('login',  'AuthController::loginForm');
$routes->post('login', 'AuthController::loginAction');
$routes->get('logout', 'AuthController::logout');

// Scaffolding de teste — será substituído pelo endpoint real de confirmação
$routes->post('teste-autorizacao-coordenador', 'AuthController::testeAutorizacao', ['filter' => 'auth']);

// ─── Dashboard — Spec 3 ───────────────────────────────────────────────
$routes->get('/', 'Home::index', ['filter' => 'auth']);

// ─── Entidades — Spec 3 ───────────────────────────────────────────────
$routes->group('entidades', ['filter' => 'auth'], function ($routes) {
    $routes->get('/',                     'EntityController::index');
    $routes->get('nova',                  'EntityController::create');
    $routes->post('nova',                 'EntityController::store');
    $routes->get('(:num)',                'EntityController::show/$1');
    $routes->post('(:num)/confirmar',     'EntityController::confirm/$1');
});

// Autocomplete JSON (AJAX, protegido)
$routes->get('api/entidades/busca', 'EntityController::search', ['filter' => 'auth']);

// ─── Relações — Spec 3 (controller futuro) ────────────────────────────
// $routes->group('relacoes', ['filter' => 'auth'], function ($routes) {
//     $routes->get('/',                 'RelationshipController::index');
//     $routes->get('nova',              'RelationshipController::create');
//     $routes->post('nova',             'RelationshipController::store');
//     $routes->post('(:num)/confirmar', 'RelationshipController::confirm/$1');
// });

// ─── Documentos — placeholder ─────────────────────────────────────────
// $routes->get('documentos', 'DocumentController::index', ['filter' => 'auth']);

// ─── Grafo dedicado — placeholder ────────────────────────────────────
// $routes->get('grafo', 'GraphController::index', ['filter' => 'auth']);
