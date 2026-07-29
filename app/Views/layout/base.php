<?php
/**
 * Cerebro — Views/layout/base.php
 * Template principal. Inclua via view('layout/base', ['title'=>..., 'content'=>...])
 * ou use $this->renderSection() com o CodeIgniter ViewRenderer.
 *
 * Variáveis esperadas:
 *   $title       (string) — título da página
 *   $breadcrumbs (array)  — [['label'=>'...', 'url'=>'...']] (opcional)
 *   $content     (string) — HTML do conteúdo principal
 *   $pageJs      (array)  — arquivos JS adicionais (opcional)
 *   $pageCss     (array)  — arquivos CSS adicionais (opcional)
 */

$auth       = new \App\Services\AuthService();
$user       = $auth->currentUser();
$role       = $user['role'] ?? 'colaborador';
$userName   = $user['name'] ?? ($user['email'] ?? 'Usuário');
$userInitial = mb_strtoupper(mb_substr($userName, 0, 1));

$pendingCount = 0; // TODO: injetar via controller
?>
<!DOCTYPE html>
<html lang="pt-BR" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="description" content="Cerebro — Ferramenta de pesquisa histórica e grafo de conhecimento">
    <meta name="csrf-token" content="<?= csrf_hash() ?>" data-name="<?= csrf_token() ?>">
    <title><?= esc($title ?? 'Cerebro') ?> — Cerebro</title>

    <!-- Bootstrap local -->
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/css/bootstrap.min.css') ?>">
    <!-- Bootstrap Icons local -->
    <link rel="stylesheet" href="<?= base_url('assets/vendor/bootstrap/icons/bootstrap-icons.min.css') ?>">
    <!-- Estilos globais -->
    <link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/layout.css') ?>">

    <?php foreach ($pageCss ?? [] as $css): ?>
    <link rel="stylesheet" href="<?= base_url('assets/css/' . esc($css)) ?>">
    <?php endforeach; ?>

    <link rel="icon" type="image/svg+xml" href="<?= base_url('favicon.ico') ?>">
</head>
<body>

<div class="cbr-layout">

    <!-- Overlay mobile -->
    <div class="cbr-sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

    <!-- ─── Sidebar ─────────────────────────────────────────────── -->
    <aside class="cbr-sidebar" id="cbr-sidebar" aria-label="Navegação principal">

        <a href="<?= base_url('/') ?>" class="cbr-sidebar-brand" aria-label="Cerebro — Ir para o início">
            <div class="brand-icon" aria-hidden="true"><i class="bi bi-diagram-3-fill"></i></div>
            <div>
                <div class="brand-name">Cerebro</div>
                <div class="brand-sub">Pesquisa histórica</div>
            </div>
        </a>

        <nav class="cbr-sidebar-nav" aria-label="Menu principal">

            <div class="cbr-nav-section">Principal</div>

            <a href="<?= base_url('/') ?>"
               class="cbr-nav-link"
               data-nav="dashboard"
               id="nav-dashboard">
                <i class="bi bi-house-door" aria-hidden="true"></i>
                Dashboard
            </a>

            <a href="<?= base_url('entidades') ?>"
               class="cbr-nav-link"
               id="nav-entities">
                <i class="bi bi-people" aria-hidden="true"></i>
                Entidades
                <?php if ($pendingCount > 0): ?>
                <span class="cbr-nav-badge" aria-label="<?= $pendingCount ?> hipóteses pendentes"><?= $pendingCount ?></span>
                <?php endif; ?>
            </a>

            <a href="<?= base_url('relacoes') ?>"
               class="cbr-nav-link"
               id="nav-relationships">
                <i class="bi bi-share" aria-hidden="true"></i>
                Relações
            </a>

            <div class="cbr-nav-section">Fontes</div>

            <a href="<?= base_url('documentos') ?>"
               class="cbr-nav-link"
               id="nav-documents">
                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                Documentos
            </a>

            <a href="<?= base_url('documentos/lote') ?>"
               class="cbr-nav-link"
               id="nav-batch-documents">
                <i class="bi bi-box-seam" aria-hidden="true"></i>
                Upload em Lote (IA)
            </a>

            <div class="cbr-nav-section">Grafo</div>

            <a href="<?= base_url('grafo') ?>"
               class="cbr-nav-link"
               id="nav-graph">
                <i class="bi bi-diagram-3" aria-hidden="true"></i>
                Visualizar grafo
            </a>

        </nav>

        <div class="cbr-sidebar-footer">
            <a href="<?= base_url('logout') ?>" class="cbr-user-chip" aria-label="Sair da conta">
                <div class="cbr-user-avatar" aria-hidden="true"><?= esc($userInitial) ?></div>
                <div>
                    <div class="cbr-user-name"><?= esc(mb_substr($userName, 0, 18)) ?></div>
                    <div class="cbr-user-role"><?= esc($role) ?></div>
                </div>
                <i class="bi bi-box-arrow-right" aria-hidden="true"></i>
            </a>
        </div>
    </aside>

    <!-- ─── Main ────────────────────────────────────────────────── -->
    <div class="cbr-main">

        <!-- Topbar -->
        <header class="cbr-topbar" role="banner">
            <button class="cbr-menu-toggle"
                    id="menu-toggle"
                    aria-controls="cbr-sidebar"
                    aria-expanded="false"
                    aria-label="Abrir menu">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>

            <div class="cbr-topbar-breadcrumb" aria-label="Localização">
                <?php if (!empty($breadcrumbs)): ?>
                    <?php foreach ($breadcrumbs as $i => $crumb): ?>
                        <?php if ($i < count($breadcrumbs) - 1): ?>
                            <a href="<?= esc($crumb['url']) ?>"><?= esc($crumb['label']) ?></a>
                            <i class="bi bi-chevron-right" aria-hidden="true"></i>
                        <?php else: ?>
                            <span class="current" aria-current="page"><?= esc($crumb['label']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <span class="current"><?= esc($title ?? 'Dashboard') ?></span>
                <?php endif; ?>
            </div>

            <div class="cbr-topbar-actions">
                <?php if ($role === 'coordenador'): ?>
                <a href="<?= base_url('entidades/nova') ?>"
                   class="btn btn-primary btn-sm d-none d-lg-inline-flex align-items-center gap-1"
                   id="topbar-new-entity">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i>
                    Nova entidade
                </a>
                <?php endif; ?>

                <button id="theme-toggle"
                        aria-label="Mudar tema"
                        data-bs-toggle="tooltip"
                        data-bs-title="Alternar tema">
                    <i class="bi bi-sun" aria-hidden="true"></i>
                </button>
            </div>
        </header>

        <!-- Flash messages -->
        <?php if (session('success') || session('error') || session('info')): ?>
        <div class="px-3 pt-3" role="alert" aria-live="polite">
            <?php if (session('success')): ?>
            <div class="alert alert-success d-flex align-items-center gap-2" data-auto-dismiss="4000">
                <i class="bi bi-check-circle-fill" aria-hidden="true"></i>
                <?= esc(session('success')) ?>
            </div>
            <?php endif; ?>
            <?php if (session('error')): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2" data-auto-dismiss="5000">
                <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                <?= esc(session('error')) ?>
            </div>
            <?php endif; ?>
            <?php if (session('info')): ?>
            <div class="alert alert-info d-flex align-items-center gap-2" data-auto-dismiss="4000">
                <i class="bi bi-info-circle-fill" aria-hidden="true"></i>
                <?= esc(session('info')) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Conteúdo -->
        <main class="cbr-content" id="main-content" tabindex="-1">
            <?= $content ?? '' ?>
        </main>

    </div><!-- /.cbr-main -->

</div><!-- /.cbr-layout -->

<!-- FAB mobile -->
<a href="<?= base_url('entidades/nova') ?>"
   class="cbr-fab"
   aria-label="Nova entidade"
   id="cbr-fab"
   data-bs-toggle="tooltip"
   data-bs-title="Nova entidade"
   data-bs-placement="left">
    <i class="bi bi-plus-lg" aria-hidden="true"></i>
</a>

<!-- Bottom Nav mobile -->
<nav class="cbr-bottom-nav" aria-label="Navegação rápida">
    <div class="cbr-bottom-nav-inner">
        <a href="<?= base_url('/') ?>"      class="cbr-bottom-item" data-nav="dashboard" id="bnav-dashboard">
            <i class="bi bi-house-door" aria-hidden="true"></i>
            <span>Home</span>
        </a>
        <a href="<?= base_url('entidades') ?>" class="cbr-bottom-item" id="bnav-entities">
            <i class="bi bi-people" aria-hidden="true"></i>
            <span>Entidades</span>
            <?php if ($pendingCount > 0): ?>
            <span class="cbr-bottom-badge" aria-label="<?= $pendingCount ?> pendentes"><?= $pendingCount ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= base_url('relacoes') ?>"   class="cbr-bottom-item" id="bnav-relationships">
            <i class="bi bi-share" aria-hidden="true"></i>
            <span>Relações</span>
        </a>
        <a href="<?= base_url('grafo') ?>"      class="cbr-bottom-item" id="bnav-graph">
            <i class="bi bi-diagram-3" aria-hidden="true"></i>
            <span>Grafo</span>
        </a>
        <a href="<?= base_url('documentos') ?>" class="cbr-bottom-item" id="bnav-documents">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
            <span>Docs</span>
        </a>
    </div>
</nav>

<!-- Scripts -->
<script src="<?= base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js') ?>"></script>
<script src="<?= base_url('assets/js/app.js') ?>"></script>

<?php foreach ($pageJs ?? [] as $js): ?>
<script src="<?= base_url('assets/js/' . esc($js)) ?>"></script>
<?php endforeach; ?>

</body>
</html>
