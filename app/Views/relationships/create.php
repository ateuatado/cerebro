<?php
/**
 * Cerebro — Views/relationships/create.php
 * Formulário de nova relação: origem → tipo → destino → fonte
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';
$prefillOriginId   = (int)($_GET['origem']  ?? 0);
$prefillOriginName = '';
if ($prefillOriginId > 0 && !empty($prefillEntity)) {
    $prefillOriginName = $prefillEntity['name'] ?? '';
}

$commonTypes = [
    'participou_de', 'foi_preso_em', 'foi_morto_em', 'trabalhou_em',
    'morou_em', 'conhecia', 'foi_acusado_por', 'liderou', 'estava_presente_em',
    'fundou', 'pertencia_a', 'foi_julgado_por', 'denunciou', 'foi_mencionado_em',
];

ob_start();
?>

<div class="fade-in-up cbr-form">

    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">Nova relação</h1>
            <p class="cbr-page-subtitle">
                Toda relação deve ser rastreada a uma fonte documental primária
            </p>
        </div>
    </div>

    <!-- Alerta de rastreabilidade -->
    <div class="alert d-flex gap-2 align-items-start mb-3"
         style="background:var(--cbr-primary-dim);border:1px solid rgba(124,106,247,.25);color:var(--cbr-text);font-size:.875rem">
        <i class="bi bi-info-circle-fill flex-shrink-0" style="color:var(--cbr-primary)" aria-hidden="true"></i>
        <div>
            <strong>Princípio de rastreabilidade:</strong>
            toda relação precisa de um documento-fonte.
            Conexões sem fonte são automaticamente salvas como <em>hipótese</em>
            e precisam de revisão da coordenadora.
        </div>
    </div>

    <form action="<?= base_url('relacoes/nova') ?>" method="post" id="rel-form" novalidate>
        <?= csrf_field() ?>

        <!-- ─── Bloco 1: Entidades ─────────────────────────────── -->
        <div class="cbr-form-card">
            <div class="cbr-form-section-title">
                <i class="bi bi-people" aria-hidden="true"></i>
                Entidades da relação
            </div>

            <div class="row g-3">
                <!-- Entidade origem -->
                <div class="col-12 col-md-5">
                    <label for="source-input" class="form-label">Entidade origem *</label>
                    <div class="cbr-autocomplete-wrap"
                         data-autocomplete="<?= base_url('api/entidades/busca') ?>"
                         data-input-id="source-input"
                         data-hidden-id="source-entity-id"
                         data-results-id="source-results">
                        <input type="text"
                               id="source-input"
                               class="form-control"
                               placeholder="Buscar entidade…"
                               value="<?= esc($prefillOriginName) ?>"
                               autocomplete="off"
                               required>
                        <div id="source-results" class="cbr-autocomplete-results"></div>
                    </div>
                    <input type="hidden" name="source_entity_id" id="source-entity-id"
                           value="<?= $prefillOriginId ?: '' ?>">
                </div>

                <!-- Direção -->
                <div class="col-12 col-md-2 d-flex flex-column align-items-center justify-content-end pb-1">
                    <label class="form-label" style="text-align:center">Direção</label>
                    <div class="cbr-filter-chips" style="justify-content:center">
                        <label class="cbr-chip" id="dir-directed"
                               style="cursor:pointer;user-select:none">
                            <input type="radio" name="direction" value="directed"
                                   checked style="display:none">
                            <i class="bi bi-arrow-right" aria-hidden="true"></i> Dirigida
                        </label>
                        <label class="cbr-chip" id="dir-symmetric"
                               style="cursor:pointer;user-select:none">
                            <input type="radio" name="direction" value="symmetric"
                                   style="display:none">
                            <i class="bi bi-arrow-left-right" aria-hidden="true"></i> Simétrica
                        </label>
                    </div>
                </div>

                <!-- Entidade destino -->
                <div class="col-12 col-md-5">
                    <label for="target-input" class="form-label">Entidade destino *</label>
                    <div class="cbr-autocomplete-wrap"
                         data-autocomplete="<?= base_url('api/entidades/busca') ?>"
                         data-input-id="target-input"
                         data-hidden-id="target-entity-id"
                         data-results-id="target-results">
                        <input type="text"
                               id="target-input"
                               class="form-control"
                               placeholder="Buscar entidade…"
                               autocomplete="off"
                               required>
                        <div id="target-results" class="cbr-autocomplete-results"></div>
                    </div>
                    <input type="hidden" name="target_entity_id" id="target-entity-id">
                </div>
            </div>
        </div>

        <!-- ─── Bloco 2: Tipo e confiança ─────────────────────── -->
        <div class="cbr-form-card">
            <div class="cbr-form-section-title">
                <i class="bi bi-tag" aria-hidden="true"></i>
                Tipo e confiança
            </div>

            <div class="row g-3">
                <div class="col-12 col-md-7">
                    <label for="relationship-type" class="form-label">Tipo de relação *</label>
                    <input type="text"
                           id="relationship-type"
                           name="relationship_type"
                           class="form-control"
                           list="rel-types-list"
                           placeholder="Ex: participou_de, conhecia…"
                           required
                           autocomplete="off">
                    <datalist id="rel-types-list">
                        <?php foreach ($commonTypes as $t): ?>
                        <option value="<?= esc($t) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text">Use snake_case. Escolha um tipo existente ou crie um novo.</div>
                </div>

                <div class="col-12 col-md-5">
                    <label class="form-label" for="confidence-slider">
                        Nível de confiança:
                        <span class="cbr-confidence-display">
                            <span id="confidence-pct" data-pct>75</span>%
                        </span>
                    </label>
                    <input type="range"
                           id="confidence-slider"
                           name="confidence_pct"
                           class="cbr-confidence-slider"
                           min="0" max="100" value="75" step="5"
                           data-display="confidence-display-wrap"
                           aria-label="Nível de confiança da relação">
                    <div class="d-flex justify-content-between mt-1">
                        <span style="font-size:.6875rem;color:var(--cbr-text-subtle)">0% — Especulação</span>
                        <span style="font-size:.6875rem;color:var(--cbr-text-subtle)">100% — Certeza</span>
                    </div>
                    <input type="hidden" name="confidence" id="confidence-hidden" value="0.75">
                </div>

                <div class="col-12 col-md-6">
                    <label for="rel-status" class="form-label">Status</label>
                    <select id="rel-status" name="status" class="form-select">
                        <option value="hypothesis">🟡 Hipótese (revisão pendente)</option>
                        <?php if ($role === 'coordenador'): ?>
                        <option value="confirmed">✅ Confirmada</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- ─── Bloco 3: Fonte documental ─────────────────────── -->
        <div class="cbr-form-card">
            <div class="cbr-form-section-title">
                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                Fonte documental
                <span class="ms-1" style="font-size:.6875rem;font-weight:400;color:var(--cbr-hypothesis)">
                    Obrigatório — Princípio I
                </span>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <label for="doc-input" class="form-label">Documento-fonte *</label>
                    <div class="cbr-autocomplete-wrap"
                         data-autocomplete="<?= base_url('api/entidades/busca') ?>"
                         data-input-id="doc-input"
                         data-hidden-id="source-document-id"
                         data-results-id="doc-results">
                        <input type="text"
                               id="doc-input"
                               class="form-control"
                               placeholder="Buscar documento-fonte…"
                               autocomplete="off"
                               required>
                        <div id="doc-results" class="cbr-autocomplete-results"></div>
                    </div>
                    <input type="hidden" name="source_document_id" id="source-document-id">
                    <div class="form-text">
                        Busque pelo nome do documento. Se não existir, <a href="<?= base_url('entidades/nova') ?>">cadastre-o primeiro</a>.
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <label for="ref-pagina" class="form-label">Página / folio</label>
                    <input type="text" id="ref-pagina" name="source_reference[pagina]"
                           class="form-control" placeholder="Ex: fl. 23v">
                </div>

                <div class="col-12 col-md-6">
                    <label for="ref-trecho" class="form-label">Trecho / citação</label>
                    <input type="text" id="ref-trecho" name="source_reference[trecho]"
                           class="form-control" placeholder="Trecho exato ou descrição">
                </div>

                <div class="col-12">
                    <label for="ref-nota" class="form-label">Nota de localização</label>
                    <textarea id="ref-nota" name="source_reference[nota]"
                              class="form-control"
                              rows="2"
                              placeholder="Observações adicionais sobre a localização na fonte…"></textarea>
                </div>
            </div>
        </div>

        <!-- Ações -->
        <div class="cbr-form-actions">
            <a href="<?= base_url('relacoes') ?>"
               class="btn btn-outline-secondary d-flex align-items-center gap-1"
               id="btn-cancel-rel">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2" id="btn-save-rel">
                <i class="bi bi-check-lg" aria-hidden="true"></i> Salvar relação
            </button>
        </div>

    </form>
</div>

<script>
// Atualizar confidence hidden ao mover o slider
(function() {
    var slider = document.getElementById('confidence-slider');
    var hidden = document.getElementById('confidence-hidden');
    var display = document.getElementById('confidence-pct');
    function update() {
        var pct = parseInt(slider.value);
        if (display) display.textContent = pct;
        if (hidden)  hidden.value = (pct / 100).toFixed(2);
        slider.style.setProperty('--slider-pct', pct + '%');
    }
    slider.addEventListener('input', update);
    update();

    // Toggle direção visual
    document.querySelectorAll('input[name="direction"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            document.querySelectorAll('#dir-directed, #dir-symmetric').forEach(function(lbl) {
                lbl.classList.remove('active');
            });
            this.closest('label').classList.add('active');
        });
    });
    document.getElementById('dir-directed') &&
        document.getElementById('dir-directed').classList.add('active');
})();
</script>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'      => 'Nova relação',
    'breadcrumbs'=> [
        ['label'=>'Dashboard', 'url'=>base_url('/')],
        ['label'=>'Relações',  'url'=>base_url('relacoes')],
        ['label'=>'Nova',      'url'=>''],
    ],
    'content'    => $content,
    'pageCss'    => ['entities.css', 'forms.css'],
    'pageJs'     => ['forms.js'],
]);
?>
