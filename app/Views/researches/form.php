<?php

/**
 * Cerebro — Views/researches/form.php
 * Formulário de Criação e Edição de Pesquisa
 */

$isEdit    = $isEdit ?? false;
$research  = $research ?? null;
$actionUrl = $isEdit 
    ? base_url('pesquisas/' . $research['id'] . '/editar')
    : base_url('pesquisas/nova');

ob_start();
?>

<div class="fade-in-up">
    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title"><?= $isEdit ? 'Editar Pesquisa' : 'Nova Pesquisa' ?></h1>
            <p class="cbr-page-subtitle">
                <?= $isEdit ? 'Atualize as informações da pesquisa histórica' : 'Cadastre um novo tema de investigação histórica' ?>
            </p>
        </div>
        <div>
            <a href="<?= base_url('pesquisas') ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </div>

    <?php if (session()->getFlashdata('errors')): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                    <li><?= esc($error) ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-12 col-md-8 col-lg-6">
            <div class="cbr-card">
                <form action="<?= $actionUrl ?>" method="POST">
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label for="title" class="form-label text-white fw-bold">Título / Tema da Pesquisa *</label>
                        <input type="text" 
                               class="form-control bg-dark text-white border-secondary" 
                               id="title" 
                               name="title" 
                               value="<?= old('title', $research['title'] ?? '') ?>" 
                               placeholder="Ex: Baby de Andrade" 
                               required>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="form-label text-white fw-bold">Descrição / Escopo Histórico</label>
                        <textarea class="form-control bg-dark text-white border-secondary" 
                                  id="description" 
                                  name="description" 
                                  rows="4" 
                                  placeholder="Detalhamento sobre a linha de pesquisa, contexto ou período..."><?= old('description', $research['description'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i> <?= $isEdit ? 'Salvar Alterações' : 'Criar Pesquisa' ?>
                        </button>
                        <a href="<?= base_url('pesquisas') ?>" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'       => $isEdit ? 'Editar Pesquisa' : 'Nova Pesquisa',
    'breadcrumbs' => [
        ['label' => 'Dashboard', 'url' => base_url('/')],
        ['label' => 'Pesquisas', 'url' => base_url('pesquisas')],
        ['label' => $isEdit ? 'Editar' : 'Nova', 'url' => '']
    ],
    'content'     => $content,
]);
?>
