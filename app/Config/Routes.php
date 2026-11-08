<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ─── Auth — Spec 2 ────────────────────────────────────────────────────
$routes->get('login',  'AuthController::loginForm');
$routes->post('login', 'AuthController::loginAction');
$routes->get('logout', 'AuthController::logout');
$routes->get('senha/esqueci',  'AuthController::forgotForm');
$routes->post('senha/esqueci', 'AuthController::forgotAction');
$routes->get('senha/redefinir/(:segment)',  'AuthController::resetForm/$1');
$routes->post('senha/redefinir/(:segment)', 'AuthController::resetAction/$1');
$routes->get('senha',  'AuthController::changePasswordForm', ['filter' => 'auth']);
$routes->post('senha', 'AuthController::changePasswordAction', ['filter' => 'auth']);
$routes->get('usuarios', 'AuthController::users', ['filter' => 'auth']);
$routes->get('usuarios/novo', 'AuthController::createUserForm', ['filter' => 'auth']);
$routes->post('usuarios/novo', 'AuthController::createUserAction', ['filter' => 'auth']);

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
    $routes->get('(:num)/editar',         'EntityController::edit/$1');
    $routes->post('(:num)/editar',        'EntityController::update/$1');
    $routes->post('(:num)/visibilidade-grafo', 'EntityController::setGraphVisibility/$1');
    $routes->post('(:num)/confirmar',     'EntityController::confirm/$1');
    $routes->post('(:num)/atributos',         'EntityController::addAttribute/$1');
    $routes->post('(:num)/atributos/remover', 'EntityController::removeAttribute/$1');
});

// Autocomplete JSON (AJAX, protegido)
$routes->get('api/entidades/busca', 'EntityController::search', ['filter' => 'auth']);

// Vocabulário controlado de atributos por tipo — Spec 11
$routes->get('api/vocabulario-atributos', 'EntityController::getAttributeVocabulary', ['filter' => 'auth']);

// ─── Relações ─────────────────────────────────────────────────────────
$routes->group('relacoes', ['filter' => 'auth'], function ($routes) {
    $routes->get('/',                     'RelationshipController::index');
    $routes->get('nova',                  'RelationshipController::create');
    $routes->post('nova',                 'RelationshipController::store');
    $routes->post('(:num)/confirmar',     'RelationshipController::confirm/$1');
});

// ─── Grafo dedicado ───────────────────────────────────────────────────
$routes->get('grafo', 'GraphController::index', ['filter' => 'auth']);

// ─── Pesquisas / Assuntos — Spec 9 ─────────────────────────────────────
$routes->group('pesquisas', ['filter' => 'auth'], function ($routes) {
    $routes->get('/',                     'ResearchProjectController::index');
    $routes->get('nova',                  'ResearchProjectController::create');
    $routes->post('nova',                 'ResearchProjectController::store');
    $routes->get('(:num)/editar',         'ResearchProjectController::edit/$1');
    $routes->post('(:num)/editar',        'ResearchProjectController::update/$1');
    $routes->post('(:num)/deletar',       'ResearchProjectController::delete/$1');
    $routes->get('(:num)/documentos',     'ResearchProjectController::manageDocuments/$1');
    $routes->post('(:num)/documentos',    'ResearchProjectController::saveDocuments/$1');
});

// ─── Documentos & Upload em Lote ──────────────────────────────────────
$routes->get('documentos',      'EntityController::documents', ['filter' => 'auth']);
$routes->get('documentos/lote', 'BatchIngestController::index', ['filter' => 'auth']);

$routes->group('documentos', ['filter' => 'auth'], function ($routes) {
    $routes->get('(:num)/arquivo',          'EntityController::serveFile/$1');
    $routes->post('reprocessar-tudo',       'ExtractionController::reprocessAll');
    $routes->post('(:num)/extrair',         'ExtractionController::extract/$1');
    $routes->post('(:num)/vincular-arquivo', 'ExtractionController::attachFile/$1');
    $routes->get('(:num)/revisar',          'DocumentReviewController::review/$1');
    $routes->post('(:num)/salvar-pesquisas', 'DocumentReviewController::updateResearches/$1');
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

// ─── Mesclagem de Entidades Duplicadas ───────────────────────────────
$routes->post('entidades/merge',                           'EntityController::merge', ['filter' => 'auth']);

$routes->group('biblioteca', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'LibraryController::index');
    $routes->get('nova', 'LibraryController::form');
    $routes->post('nova', 'LibraryController::save');
    $routes->get('(:num)', 'LibraryController::show/$1');
    $routes->get('(:num)/editar', 'LibraryController::form/$1');
    $routes->post('(:num)/editar', 'LibraryController::save/$1');
    $routes->get('(:num)/notas/nova', 'BibliographicNoteController::form/$1');
    $routes->post('(:num)/notas/nova', 'BibliographicNoteController::save/$1');
    $routes->get('(:num)/notas/(:num)/editar', 'BibliographicNoteController::form/$1/$2');
    $routes->post('(:num)/notas/(:num)', 'BibliographicNoteController::save/$1/$2');
    $routes->post('(:num)/notas/(:num)/excluir', 'BibliographicNoteController::delete/$1/$2');
    $routes->post('(:num)/excluir', 'LibraryController::delete/$1');
});

$routes->group('caderno/diario', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'ResearchDiaryController::index');
    $routes->get('novo', 'ResearchDiaryController::form');
    $routes->post('novo', 'ResearchDiaryController::save');
    $routes->get('(:num)/editar', 'ResearchDiaryController::form/$1');
    $routes->post('(:num)/editar', 'ResearchDiaryController::save/$1');
    $routes->post('(:num)/excluir', 'ResearchDiaryController::delete/$1');
});

$routes->group('caderno/fichamentos', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'DocumentNoteController::index');
    $routes->get('bibliograficos', 'BibliographicNoteController::index');
    $routes->get('novo', 'DocumentNoteController::form');
    $routes->post('novo', 'DocumentNoteController::save');
    $routes->get('(:num)/editar', 'DocumentNoteController::form/$1');
    $routes->post('(:num)/editar', 'DocumentNoteController::save/$1');
});

$routes->group('produtos', ['filter' => 'auth'], static function ($routes) {
    $routes->get('/', 'IntellectualProductController::index');
    $routes->get('novo', 'IntellectualProductController::form');
    $routes->post('novo', 'IntellectualProductController::save');
    $routes->get('(:num)', 'IntellectualProductController::show/$1');
    $routes->get('(:num)/bibliografia', 'ProductBibliographyController::index/$1');
    $routes->post('(:num)/bibliografia/adicionar', 'ProductBibliographyController::attach/$1');
    $routes->post('(:num)/bibliografia/(:num)/retirar', 'ProductBibliographyController::detach/$1/$2');
    $routes->get('(:num)/partes/nova', 'ProductPartController::form/$1');
    $routes->post('(:num)/partes/nova', 'ProductPartController::save/$1');
    $routes->get('(:num)/partes/(:num)/editar', 'ProductPartController::form/$1/$2');
    $routes->post('(:num)/partes/(:num)/editar', 'ProductPartController::save/$1/$2');
    $routes->post('(:num)/partes/(:num)/mover', 'ProductPartController::move/$1/$2');
    $routes->post('(:num)/partes/(:num)/excluir', 'ProductPartController::delete/$1/$2');
    $routes->post('(:num)/partes/(:num)/obras/adicionar', 'ProductPartController::attachWork/$1/$2');
    $routes->post('(:num)/partes/(:num)/obras/(:num)/retirar', 'ProductPartController::detachWork/$1/$2/$3');
});
