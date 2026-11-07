<?php use App\Models\DocumentNoteModel; ?>
<?php ob_start(); ?>
<h1>Caderno de pesquisa</h1>
<?= view('diary/_nav', ['tab' => 'notes']) ?>
<?= view('diary/_note_nav', ['collection' => 'documentary']) ?>
<h2>Fichamentos documentais</h2>
<p>Registre excertos e observações sobre documentos do acervo.</p>
<a class="btn btn-primary mb-3" href="<?= base_url('caderno/fichamentos/novo') ?>">Criar ficha</a>
<form method="get" action="<?= base_url('caderno/fichamentos') ?>" class="row g-2 align-items-end mb-3">
    <div class="col-sm">
        <label class="form-label" for="notes-query">Buscar por fonte ou texto</label>
        <input class="form-control" id="notes-query" name="q" maxlength="255" value="<?= esc($query) ?>">
    </div>
    <div class="col-sm-auto">
        <label class="form-label" for="notes-kind">Tipo</label>
        <select class="form-select" id="notes-kind" name="tipo">
            <option value="">Todos</option>
            <?php foreach (DocumentNoteModel::KINDS as $key => $label): ?>
                <option value="<?= esc($key) ?>" <?= $kind === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="col-sm-auto"><button class="btn btn-secondary">Buscar</button></div>
</form>
<?php if ($notes === []): ?><p>Nenhuma ficha encontrada.</p><?php endif ?>
<?php foreach ($notes as $note): ?>
    <article class="cbr-card mb-3 p-3">
        <h3 class="h5"><?= esc($note['document_label']) ?></h3>
        <p><?= esc(DocumentNoteModel::KINDS[$note['kind']]) ?><?= $note['locator'] !== '' ? ' · ' . esc($note['locator']) : '' ?></p>
        <?php if ($note['excerpt'] !== ''): ?>
            <div class="fw-semibold">Excerto</div>
            <blockquote><?= nl2br(esc($note['excerpt'])) ?></blockquote>
        <?php endif ?>
        <?php if ($note['observations'] !== ''): ?>
            <div class="fw-semibold">Observações</div>
            <p><?= nl2br(esc($note['observations'])) ?></p>
        <?php endif ?>
        <div>
            <a href="<?= base_url('caderno/fichamentos/' . $note['id'] . '/editar') ?>">Editar ficha</a>
            <?php if ($note['document_id'] !== null): ?>
                <?php $pageLink = preg_match('/^p\. ([1-9][0-9]{0,8})$/D', $note['locator'], $pageMatch)
                    ? '?pagina=' . $pageMatch[1] : ''; ?>
                · <a href="<?= base_url('documentos/' . $note['document_id'] . '/revisar') . $pageLink ?>"><?= $pageLink !== '' ? 'Voltar à página' : 'Abrir documento' ?></a>
            <?php else: ?>
                · Documento removido do acervo
            <?php endif ?>
        </div>
    </article>
<?php endforeach ?>
<?php if ($pager->getPageCount() > 1): ?><?= $pager->only(['q', 'tipo'])->links() ?><?php endif ?>
<?= view('layout/base', ['title' => 'Fichamentos documentais', 'content' => ob_get_clean()]) ?>
