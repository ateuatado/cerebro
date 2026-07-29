<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ─── Auth — Spec 2 ────────────────────────────────────────────────────
$routes->get('login',  'AuthController::loginForm');
$routes->post('login', 'AuthController::loginAction');
$routes->get('logout', 'AuthController::logout');

// Scaffolding de teste
$routes->post('teste-autorizacao-coordenador', 'AuthController::testeAutorizacao', ['filter' => 'auth']);

// ─── Dashboard ────────────────────────────────────────────────────────
$routes->get('/', 'Home::index', ['filter' => 'auth']);

// ─── Entidades ────────────────────────────────────────────────────────
$routes->group('entidades', ['filter' => 'auth'], function ($routes) {
    $routes->get('/',                     'EntityController::index');
    $routes->get('nova',                  'EntityController::create');
    $routes->post('nova',                 'EntityController::store');
    $routes->get('(:num)',                'EntityController::show/$1');
    $routes->post('(:num)/confirmar',     'EntityController::confirm/$1');
});

// Autocomplete JSON (AJAX, protegido)
$routes->get('api/entidades/busca', 'EntityController::search', ['filter' => 'auth']);

// ─── Relações ─────────────────────────────────────────────────────────
$routes->group('relacoes', ['filter' => 'auth'], function ($routes) {
    $routes->get('/',                     'RelationshipController::index');
    $routes->get('nova',                  'RelationshipController::create');
    $routes->post('nova',                 'RelationshipController::store');
    $routes->post('(:num)/confirmar',     'RelationshipController::confirm/$1');
});

// ─── Grafo dedicado ───────────────────────────────────────────────────
$routes->get('grafo', 'GraphController::index', ['filter' => 'auth']);

// ─── Documentos — listagem e extração via IA ───────────────────────────
$routes->get('documentos', 'EntityController::documents', ['filter' => 'auth']);

$routes->group('documentos', ['filter' => 'auth'], function ($routes) {
    $routes->post('(:num)/extrair',       'ExtractionController::extract/$1');
    $routes->get('(:num)/revisar',        'ExtractionController::review/$1');
    $routes->post('(:num)/aprovar-todas', 'ExtractionController::approveAll/$1');
});
