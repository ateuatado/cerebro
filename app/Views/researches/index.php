<?php

/**
 * Cerebro — Views/researches/index.php
 * Listagem de Pesquisas e Linhas de Investigação Histórica
 */

$researches = $researches ?? [];

ob_start();
?>

<div class="fade-in-up">

    <!-- Page Header -->
    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">Pesquisas & Assuntos</h1>
            <p class="cbr-page-subtitle">
                Organize documentos e recortes de grafos por temas de investigação histórica
            </p>
        </div>
        <div>
            <a href="<?= base_url('pesquisas/nova') ?>" class="btn btn-primary d-flex align-items-center gap-2">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                <span>Nova Pesquisa</span>
            </a>
        </div>
    </div>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= session()->getFlashdata('success') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= session()->getFlashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($researches)): ?>
        <div class="cbr-card text-center py-5">
            <div class="mb-3 text-subtle">
                <i class="bi bi-journal-bookmark fs-1"></i>
            </div>
            <h5>Nenhuma pesquisa cadastrada</h5>
            <p class="text-subtle mb-4">Crie uma pesquisa (ex: "Baby de Andrade") para agrupar documentos e filtrar seu grafo do conhecimento.</p>
            <a href="<?= base_url('pesquisas/nova') ?>" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i> Cadastrar Primeira Pesquisa
            </a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($researches as $r): ?>
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="cbr-card h-100 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="fw-bold mb-0 text-white"><?= esc($r['title']) ?></h5>
                                <span class="badge bg-secondary rounded-pill">
                                    <i class="bi bi-file-earmark-text me-1"></i><?= $r['document_count'] ?> doc<?= $r['document_count'] != 1 ? 's' : '' ?>
                                </span>
                            </div>
                            <p class="text-subtle small mb-3">
                                <?= !empty($r['description']) ? esc($r['description']) : '<em>Sem descrição cadastrada.</em>' ?>
                            </p>
                        </div>

                        <div class="border-top pt-3 mt-3 d-flex justify-content-between align-items-center">
                            <div class="btn-group btn-group-sm">
                                <a href="<?= base_url('grafo?research_id=' . $r['id']) ?>" 
                                   class="btn btn-outline-info d-inline-flex align-items-center gap-1"
                                   title="Filtrar Grafo por esta Pesquisa">
                                    <i class="bi bi-diagram-3"></i> Grafo
                                </a>
                                <a href="<?= base_url('pesquisas/' . $r['id'] . '/documentos') ?>" 
                                   class="btn btn-outline-secondary d-inline-flex align-items-center gap-1"
                                   title="Gerenciar Documentos Vinculados">
                                    <i class="bi bi-files"></i> Documentos
                                </a>
                            </div>

                            <div class="dropdown">
                                <button class="btn btn-sm btn-link text-subtle p-0" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <i class="bi bi-three-dots-vertical fs-5"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="<?= base_url('pesquisas/' . $r['id'] . '/editar') ?>">
                                            <i class="bi bi-pencil me-2"></i>Editar
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="<?= base_url('pesquisas/' . $r['id'] . '/deletar') ?>" method="POST" onsubmit="return confirm('Excluir esta pesquisa? Os documentos e entidades permanecerão salvos no acervo.');">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="dropdown-item text-danger">
                                                <i class="bi bi-trash me-2"></i>Excluir
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'       => 'Pesquisas & Assuntos',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => base_url('/')],
        ['label' => 'Pesquisas', 'url' => '']
    ],
    'content'     => $content,
]);
?>
