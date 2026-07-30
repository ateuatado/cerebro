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

// ─── Documentos & Upload em Lote ──────────────────────────────────────
$routes->get('documentos',      'EntityController::documents', ['filter' => 'auth']);
$routes->get('documentos/lote', 'BatchIngestController::index', ['filter' => 'auth']);

$routes->group('documentos', ['filter' => 'auth'], function ($routes) {
    $routes->get('(:num)/arquivo',          'EntityController::serveFile/$1');
    $routes->post('reprocessar-tudo',       'ExtractionController::reprocessAll');
    $routes->post('(:num)/extrair',         'ExtractionController::extract/$1');
    $routes->post('(:num)/vincular-arquivo', 'ExtractionController::attachFile/$1');
    $routes->get('(:num)/revisar',          'DocumentReviewController::review/$1');
    $routes->post('(:num)/aprovar-todas',   'ExtractionController::approveAll/$1');
});

// API de Upload em Lote (AJAX)
$routes->post('api/documentos/upload-item', 'BatchIngestController::uploadItem', ['filter' => 'auth']);
$routes->post('documentos/api/documentos/upload-item', 'BatchIngestController::uploadItem', ['filter' => 'auth']);

// ─── Workspace Interativo de Transcrição Histórica — Spec 7 & Spec 8 ──
$routes->get('api/documentos/(:num)/pagina/(:num)/imagem',                  'DocumentReviewController::getPageImage/$1/$2', ['filter' => 'auth']);
$routes->post('api/documentos/(:num)/pagina/(:num)/girar',                   'DocumentReviewController::rotatePage/$1/$2', ['filter' => 'auth']);
$routes->post('api/documentos/(:num)/pagina/(:num)/extrair-regiao',          'DocumentReviewController::extractRegion/$1/$2', ['filter' => 'auth']);
$routes->post('api/documentos/(:num)/pagina/(:num)/extrair-entidades-regiao', 'DocumentReviewController::extractEntitiesFromRegion/$1/$2', ['filter' => 'auth']);
$routes->post('api/documentos/(:num)/confirmar-entidades-regiao',             'DocumentReviewController::confirmRegionEntities/$1', ['filter' => 'auth']);
$routes->post('api/documentos/(:num)/pagina/(:num)/salvar-texto',            'DocumentReviewController::savePageText/$1/$2', ['filter' => 'auth']);

// ─── Documentos Pendentes de Extração — Spec 6 ───────────────────────
$routes->get('documentos/pendentes',                       'PendingExtractionController::index', ['filter' => 'auth']);
$routes->post('api/documentos/pendentes/salvar-texto',     'PendingExtractionController::updateText', ['filter' => 'auth']);
$routes->post('api/documentos/pendentes/extrair/(:num)',   'PendingExtractionController::extractSingle/$1', ['filter' => 'auth']);
$routes->post('api/documentos/pendentes/processar-todos',  'PendingExtractionController::extractBatch', ['filter' => 'auth']);

// ─── Exclusão em Cascata e Limpeza Total ─────────────────────────────
$routes->post('entidades/(:num)/deletar',                  'EntityController::delete/$1', ['filter' => 'auth']);
$routes->post('documentos/(:num)/deletar',                 'EntityController::deleteDocument/$1', ['filter' => 'auth']);
$routes->post('api/limpar-banco-total',                    'EntityController::clearAllIngestions', ['filter' => 'auth']);
