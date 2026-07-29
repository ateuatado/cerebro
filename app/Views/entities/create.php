<?php
/**
 * Cerebro — Views/entities/create.php
 * Formulário em 3 steps: Tipo → Dados → Revisão
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';

ob_start();
?>

<div class="fade-in-up cbr-form">

    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">Nova entidade</h1>
            <p class="cbr-page-subtitle">Preencha os dados em 3 passos</p>
        </div>
    </div>

    <!-- Stepper -->
    <div class="cbr-stepper" role="tablist" aria-label="Passos do formulário">
        <div class="cbr-step active" role="tab" aria-selected="true" id="step-tab-1">
            <div class="cbr-step-dot" aria-hidden="true">1</div>
            <div class="cbr-step-label">Tipo</div>
        </div>
        <div class="cbr-step" role="tab" aria-selected="false" id="step-tab-2">
            <div class="cbr-step-dot" aria-hidden="true">2</div>
            <div class="cbr-step-label">Dados</div>
        </div>
        <div class="cbr-step" role="tab" aria-selected="false" id="step-tab-3">
            <div class="cbr-step-dot" aria-hidden="true">3</div>
            <div class="cbr-step-label">Revisão</div>
        </div>
    </div>

    <form action="<?= base_url('entidades/nova') ?>" method="post" id="entity-form" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="type" id="entity-type-input" value="">

        <!-- ─── Step 1: Tipo ────────────────────────────────────── -->
        <div class="cbr-step-panel active" id="panel-1" role="tabpanel" aria-labelledby="step-tab-1">
            <div class="cbr-form-card">
                <div class="cbr-form-section-title">
                    <i class="bi bi-tag" aria-hidden="true"></i>
                    Que tipo de entidade é esta?
                </div>

                <div class="cbr-type-selector" role="radiogroup" aria-label="Tipo de entidade">

                    <label class="cbr-type-option type-person" data-type="person" id="type-opt-person">
                        <input type="radio" name="_type_ui" value="person" aria-label="Pessoa">
                        <i class="bi bi-person-fill type-icon" aria-hidden="true"></i>
                        <div class="type-label">Pessoa</div>
                        <div class="type-desc">Indivíduo histórico</div>
                    </label>

                    <label class="cbr-type-option type-location" data-type="location" id="type-opt-location">
                        <input type="radio" name="_type_ui" value="location" aria-label="Local">
                        <i class="bi bi-geo-alt-fill type-icon" aria-hidden="true"></i>
                        <div class="type-label">Local</div>
                        <div class="type-desc">Cidade, região, endereço</div>
                    </label>

                    <label class="cbr-type-option type-event" data-type="event" id="type-opt-event">
                        <input type="radio" name="_type_ui" value="event" aria-label="Evento">
                        <i class="bi bi-calendar-event type-icon" aria-hidden="true"></i>
                        <div class="type-label">Evento</div>
                        <div class="type-desc">Fato histórico datado</div>
                    </label>

                    <label class="cbr-type-option type-document" data-type="document" id="type-opt-document">
                        <input type="radio" name="_type_ui" value="document" aria-label="Documento">
                        <i class="bi bi-file-earmark-text type-icon" aria-hidden="true"></i>
                        <div class="type-label">Documento</div>
                        <div class="type-desc">Fonte primária</div>
                    </label>

                </div>
                <div class="invalid-feedback d-block" id="type-error" style="display:none!important;color:var(--cbr-danger);font-size:.8125rem">
                    Selecione um tipo de entidade para continuar.
                </div>
            </div>
        </div>

        <!-- ─── Step 2: Dados ───────────────────────────────────── -->
        <div class="cbr-step-panel" id="panel-2" role="tabpanel" aria-labelledby="step-tab-2">
            <div class="cbr-form-card">
                <div class="cbr-form-section-title">
                    <i class="bi bi-pencil" aria-hidden="true"></i>
                    Informações gerais
                </div>

                <div class="mb-4">
                    <label for="entity-name" class="form-label">Nome *</label>
                    <input type="text"
                           id="entity-name"
                           name="name"
                           class="form-control"
                           placeholder="Nome completo ou designação"
                           required
                           autocomplete="off"
                           maxlength="255">
                    <div class="invalid-feedback">O nome é obrigatório.</div>
                </div>

                <div class="mb-4">
                    <label for="entity-status" class="form-label">Status inicial</label>
                    <select id="entity-status" name="status" class="form-select">
                        <option value="hypothesis">🟡 Hipótese (revisão pendente)</option>
                        <?php if ($role === 'coordenador'): ?>
                        <option value="confirmed">✅ Confirmado</option>
                        <?php endif; ?>
                    </select>
                    <div class="form-text">Hipóteses não são apresentadas como fatos até revisão humana.</div>
                </div>
            </div>

            <!-- Atributos dinâmicos — Pessoa -->
            <div class="cbr-form-card cbr-dynamic-fields" data-for-type="person" id="attrs-person">
                <div class="cbr-form-section-title">
                    <i class="bi bi-person-vcard" aria-hidden="true"></i>
                    Atributos da pessoa
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="attr-ocupacao" class="form-label">Ocupação / cargo</label>
                        <input type="text" id="attr-ocupacao" name="attributes[ocupacao]"
                               class="form-control" placeholder="Ex: delegado, professor"
                               data-attr-key="ocupacao">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="attr-nascimento" class="form-label">Data de nascimento</label>
                        <input type="text" id="attr-nascimento" name="attributes[nascimento]"
                               class="form-control" placeholder="YYYY-MM-DD ou aproximado"
                               data-attr-key="nascimento">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="attr-naturalidade" class="form-label">Naturalidade</label>
                        <input type="text" id="attr-naturalidade" name="attributes[naturalidade]"
                               class="form-control" placeholder="Cidade de origem"
                               data-attr-key="naturalidade">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="attr-filiacao" class="form-label">Filiação</label>
                        <input type="text" id="attr-filiacao" name="attributes[filiacao]"
                               class="form-control" placeholder="Nome do pai / da mãe"
                               data-attr-key="filiacao">
                    </div>
                    <div class="col-12">
                        <label for="attr-notas-pessoa" class="form-label">Notas adicionais</label>
                        <textarea id="attr-notas-pessoa" name="attributes[notas]"
                                  class="form-control" rows="3"
                                  placeholder="Informações complementares…"
                                  data-attr-key="notas"></textarea>
                    </div>
                </div>
            </div>

            <!-- Atributos dinâmicos — Local -->
            <div class="cbr-form-card cbr-dynamic-fields" data-for-type="location" id="attrs-location">
                <div class="cbr-form-section-title">
                    <i class="bi bi-map" aria-hidden="true"></i>
                    Atributos do local
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="attr-municipio" class="form-label">Município</label>
                        <input type="text" id="attr-municipio" name="attributes[municipio]"
                               class="form-control" placeholder="Nome do município"
                               data-attr-key="municipio">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="attr-estado" class="form-label">Estado / UF</label>
                        <input type="text" id="attr-estado" name="attributes[estado]"
                               class="form-control" placeholder="Ex: SP, RJ"
                               data-attr-key="estado">
                    </div>
                    <div class="col-12">
                        <label for="attr-descricao-local" class="form-label">Descrição</label>
                        <textarea id="attr-descricao-local" name="attributes[descricao]"
                                  class="form-control" rows="3"
                                  placeholder="Descrição do local no período histórico…"
                                  data-attr-key="descricao"></textarea>
                    </div>
                </div>
            </div>

            <!-- Atributos dinâmicos — Evento -->
            <div class="cbr-form-card cbr-dynamic-fields" data-for-type="event" id="attrs-event">
                <div class="cbr-form-section-title">
                    <i class="bi bi-calendar3" aria-hidden="true"></i>
                    Atributos do evento
                </div>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="attr-data-evento" class="form-label">Data do evento</label>
                        <input type="text" id="attr-data-evento" name="attributes[data]"
                               class="form-control" placeholder="YYYY-MM-DD ou YYYY"
                               data-attr-key="data">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="attr-tipo-evento" class="form-label">Tipo de evento</label>
                        <select id="attr-tipo-evento" name="attributes[tipo_evento]" class="form-select">
                            <option value="">Selecione…</option>
                            <option value="prisao">Prisão</option>
                            <option value="julgamento">Julgamento</option>
                            <option value="assassinato">Assassinato / morte</option>
                            <option value="manifestacao">Manifestação</option>
                            <option value="golpe">Golpe / intervenção</option>
                            <option value="decreto">Decreto / ato oficial</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="attr-descricao-evento" class="form-label">Descrição</label>
                        <textarea id="attr-descricao-evento" name="attributes[descricao]"
                                  class="form-control" rows="3"
                                  placeholder="Descrição do evento…"
                                  data-attr-key="descricao"></textarea>
                    </div>
                </div>
            </div>

            <!-- Atributos dinâmicos — Documento (vocabulário controlado AGENTS.md) -->
            <div class="cbr-form-card cbr-dynamic-fields" data-for-type="document" id="attrs-document">
                <div class="cbr-form-section-title">
                    <i class="bi bi-archive" aria-hidden="true"></i>
                    Referência bibliográfica
                    <span class="ms-1" style="font-size:.6875rem;font-weight:400;color:var(--cbr-text-subtle)">(vocabulário controlado — AGENTS.md)</span>
                </div>
                <div class="row g-3">
                    <div class="col-12">
                        <label for="attr-titulo" class="form-label">Título *</label>
                        <input type="text" id="attr-titulo" name="attributes[titulo]"
                               class="form-control" placeholder="Título formal do documento"
                               data-attr-key="titulo">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="attr-autor" class="form-label">Autor / responsável</label>
                        <input type="text" id="attr-autor" name="attributes[autor_responsavel]"
                               class="form-control" placeholder="Ex: Tribunal de Justiça…"
                               data-attr-key="autor_responsavel">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="attr-tipo-doc" class="form-label">Tipo de documento</label>
                        <select id="attr-tipo-doc" name="attributes[tipo_documento]" class="form-select">
                            <option value="">Selecione…</option>
                            <option value="processo_judicial">Processo judicial</option>
                            <option value="oficio">Ofício</option>
                            <option value="correspondencia">Correspondência</option>
                            <option value="foto">Fotografia</option>
                            <option value="periodico">Periódico</option>
                            <option value="relatorio">Relatório</option>
                            <option value="outro">Outro</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label for="attr-instituicao" class="form-label">Instituição custodiadora</label>
                        <input type="text" id="attr-instituicao" name="attributes[instituicao_custodiadora]"
                               class="form-control" placeholder="Ex: Arquivo Público do Estado de SP"
                               data-attr-key="instituicao_custodiadora">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="attr-fundo" class="form-label">Fundo arquivístico</label>
                        <input type="text" id="attr-fundo" name="attributes[localizacao_arquivistica][fundo]"
                               class="form-control" placeholder="Nome do fundo">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="attr-caixa" class="form-label">Caixa</label>
                        <input type="text" id="attr-caixa" name="attributes[localizacao_arquivistica][caixa]"
                               class="form-control" placeholder="Ex: C-1929-03">
                    </div>
                    <div class="col-12 col-md-4">
                        <label for="attr-maco" class="form-label">Maço</label>
                        <input type="text" id="attr-maco" name="attributes[localizacao_arquivistica][maco]"
                               class="form-control" placeholder="Ex: M-487">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="attr-data-doc" class="form-label">Data do documento</label>
                        <input type="text" id="attr-data-doc" name="attributes[data]"
                               class="form-control" placeholder="YYYY-MM-DD ou YYYY-MM ou YYYY">
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="attr-data-acesso" class="form-label">Data de acesso</label>
                        <input type="text" id="attr-data-acesso" name="attributes[data_acesso]"
                               class="form-control" placeholder="YYYY-MM-DD">
                    </div>
                </div>
            </div>
        </div>

        <!-- ─── Step 3: Revisão ─────────────────────────────────── -->
        <div class="cbr-step-panel" id="panel-3" role="tabpanel" aria-labelledby="step-tab-3">
            <div class="cbr-form-card">
                <div class="cbr-form-section-title">
                    <i class="bi bi-eye" aria-hidden="true"></i>
                    Revisão antes de salvar
                </div>
                <div class="alert alert-warning d-flex gap-2 align-items-start" style="background:var(--cbr-hypothesis-bg);border-color:rgba(245,158,11,.3);color:var(--cbr-hypothesis)">
                    <i class="bi bi-exclamation-triangle-fill flex-shrink-0" aria-hidden="true"></i>
                    <div>
                        A entidade será salva como <strong>hipótese</strong> por padrão.
                        Apenas o coordenador pode confirmá-la como fato após revisão.
                    </div>
                </div>

                <div class="cbr-attr-table">
                    <div class="cbr-review-item">
                        <div class="cbr-review-label">Tipo</div>
                        <div class="cbr-review-value" id="review-type">—</div>
                    </div>
                    <div class="cbr-review-item">
                        <div class="cbr-review-label">Nome</div>
                        <div class="cbr-review-value" id="review-name">—</div>
                    </div>
                    <div class="cbr-review-item">
                        <div class="cbr-review-label">Status inicial</div>
                        <div class="cbr-review-value" id="review-status">Hipótese</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botões de navegação do stepper -->
        <div class="cbr-form-actions">
            <button type="button" class="btn btn-outline-secondary d-flex align-items-center gap-2" id="step-prev" style="display:none!important">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Voltar
            </button>
            <button type="button" class="btn btn-primary d-flex align-items-center gap-2 ms-auto" id="step-next">
                Continuar <i class="bi bi-arrow-right" aria-hidden="true"></i>
            </button>
            <button type="submit" class="btn btn-primary d-flex align-items-center gap-2 ms-auto" id="step-submit" style="display:none!important">
                <i class="bi bi-check-lg" aria-hidden="true"></i> Salvar entidade
            </button>
        </div>

    </form>
</div>

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'      => 'Nova entidade',
    'breadcrumbs'=> [
        ['label' => 'Dashboard',  'url' => base_url('/')],
        ['label' => 'Entidades',  'url' => base_url('entidades')],
        ['label' => 'Nova',       'url' => ''],
    ],
    'content'    => $content,
    'pageCss'    => ['entities.css', 'forms.css'],
    'pageJs'     => ['forms.js'],
]);
?>
