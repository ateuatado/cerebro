<?php
/**
 * Cerebro — Views/entities/index.php
 * Listagem de entidades com filtros, busca e toggle cards/tabela
 */
$auth = new \App\Services\AuthService();
$role = $auth->currentUser()['role'] ?? 'colaborador';

$entities  = $entities  ?? [];
$typeIconMap = [
    'person'   => ['icon' => 'bi-person-fill',      'css' => 'person',   'bg' => 'var(--cbr-person-bg)',   'color' => 'var(--cbr-person)',   'label' => 'Pessoa'],
    'location' => ['icon' => 'bi-geo-alt-fill',      'css' => 'location', 'bg' => 'var(--cbr-location-bg)', 'color' => 'var(--cbr-location)', 'label' => 'Local'],
    'event'    => ['icon' => 'bi-calendar-event',    'css' => 'event',    'bg' => 'var(--cbr-event-bg)',    'color' => 'var(--cbr-event)',    'label' => 'Evento'],
    'document' => ['icon' => 'bi-file-earmark-text', 'css' => 'document', 'bg' => 'var(--cbr-document-bg)', 'color' => 'var(--cbr-document)', 'label' => 'Documento'],
];

ob_start();
?>

<div class="fade-in-up">

    <!-- Page header -->
    <div class="cbr-page-header">
        <div>
            <h1 class="cbr-page-title">Entidades</h1>
            <p class="cbr-page-subtitle">
                <span id="entities-count"><?= count($entities) ?></span>
                entidade<?= count($entities) !== 1 ? 's' : '' ?> no grafo
            </p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <!-- View toggle (só desktop) -->
            <div class="cbr-view-toggle d-none d-md-flex" role="group" aria-label="Modo de visualização">
                <button class="cbr-view-btn active" id="btn-view-cards" aria-label="Ver como cards">
                    <i class="bi bi-grid" aria-hidden="true"></i>
                </button>
                <button class="cbr-view-btn" id="btn-view-table" aria-label="Ver como tabela">
                    <i class="bi bi-list-ul" aria-hidden="true"></i>
                </button>
            </div>
            <a href="<?= base_url('entidades/nova') ?>"
               class="btn btn-primary d-flex align-items-center gap-2"
               id="entity-new-btn">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                <span class="d-none d-sm-inline">Nova entidade</span>
            </a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="cbr-filter-bar" role="search" aria-label="Filtros de entidades">

        <!-- Busca -->
        <div class="cbr-search-box">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search"
                   id="entity-search"
                   class="form-control"
                   placeholder="Buscar entidades…"
                   aria-label="Buscar entidades">
        </div>

        <!-- Filtros de tipo -->
        <div class="cbr-filter-chips" role="group" aria-label="Filtrar por tipo">
            <button class="cbr-chip active" data-filter-type="all">Todos</button>
            <button class="cbr-chip" data-filter-type="person">
                <i class="bi bi-person" aria-hidden="true"></i> Pessoa
            </button>
            <button class="cbr-chip" data-filter-type="location">
                <i class="bi bi-geo-alt" aria-hidden="true"></i> Local
            </button>
            <button class="cbr-chip" data-filter-type="event">
                <i class="bi bi-calendar-event" aria-hidden="true"></i> Evento
            </button>
            <button class="cbr-chip" data-filter-type="document">
                <i class="bi bi-file-earmark-text" aria-hidden="true"></i> Documento
            </button>
        </div>

        <!-- Filtros de status -->
        <div class="cbr-filter-chips" role="group" aria-label="Filtrar por status">
            <button class="cbr-chip active" data-filter-status="all">Todos os status</button>
            <button class="cbr-chip" data-filter-status="confirmed">✅ Confirmados</button>
            <button class="cbr-chip" data-filter-status="hypothesis">🟡 Hipóteses</button>
        </div>

    </div>

    <!-- ─── Visão em cards ─────────────────────────────────────── -->
    <div id="view-cards">
        <?php if (empty($entities)): ?>
        <div class="cbr-empty-state" id="entities-empty" style="display:none"></div>
        <div class="cbr-empty-state">
            <i class="bi bi-people" aria-hidden="true"></i>
            <p>Nenhuma entidade cadastrada ainda.</p>
            <a href="<?= base_url('entidades/nova') ?>" class="btn btn-primary btn-sm" id="entity-empty-cta">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Criar primeira entidade
            </a>
        </div>
        <?php else: ?>
        <div id="entities-empty" class="cbr-empty-state" style="display:none">
            <i class="bi bi-search" aria-hidden="true"></i>
            <p>Nenhuma entidade encontrada com esses filtros.</p>
        </div>

        <div class="cbr-entity-grid" id="entity-cards-grid" role="list">
            <?php foreach ($entities as $entity):
                $ti   = $typeIconMap[$entity['type']] ?? $typeIconMap['person'];
                $attrs = is_array($entity['attributes']) ? $entity['attributes'] : json_decode($entity['attributes'] ?? '{}', true) ?? [];
                $attrPreview = implode(' · ', array_map(
                    fn($v) => is_array($v) ? implode(', ', $v) : $v,
                    array_slice(array_values($attrs), 0, 3)
                ));
            ?>
            <a href="<?= base_url('entidades/' . $entity['id']) ?>"
               class="cbr-entity-card <?= esc($ti['css']) ?>"
               data-name="<?= esc(mb_strtolower($entity['name'])) ?>"
               data-type="<?= esc($entity['type']) ?>"
               data-status="<?= esc($entity['status']) ?>"
               role="listitem"
               aria-label="<?= esc($entity['name']) ?> — <?= esc($ti['label']) ?>">

                <div class="cbr-entity-header">
                    <div class="cbr-entity-icon"
                         style="background:<?= $ti['bg'] ?>;color:<?= $ti['color'] ?>"
                         aria-hidden="true">
                        <i class="bi <?= $ti['icon'] ?>"></i>
                    </div>
                    <div class="flex-1">
                        <h2 class="cbr-entity-title"><?= esc($entity['name']) ?></h2>
                    </div>
                </div>

                <div class="cbr-entity-meta">
                    <span class="badge-type <?= 'badge-' . $ti['css'] ?>"><?= esc($ti['label']) ?></span>
                    <span class="<?= $entity['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-hypothesis' ?>">
                        <?= $entity['status'] === 'confirmed' ? 'Confirmado' : 'Hipótese' ?>
                    </span>
                </div>

                <?php if ($attrPreview): ?>
                <div class="cbr-entity-attrs"><?= esc($attrPreview) ?></div>
                <?php endif; ?>

                <div class="cbr-entity-footer">
                    <span class="cbr-entity-date">
                        <?= isset($entity['created_at']) ? date('d/m/Y', strtotime($entity['created_at'])) : '' ?>
                    </span>
                    <?php if ($role === 'coordenador' && $entity['status'] === 'hypothesis'): ?>
                    <button class="btn-confirm"
                            data-confirm-entity="<?= $entity['id'] ?>"
                            data-entity-name="<?= esc($entity['name']) ?>"
                            data-confirm-url="<?= base_url('entidades/' . $entity['id'] . '/confirmar') ?>"
                            aria-label="Confirmar <?= esc($entity['name']) ?> como fato">
                        <i class="bi bi-patch-check" aria-hidden="true"></i> Confirmar
                    </button>
                    <?php endif; ?>
                </div>

            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- ─── Visão em tabela (desktop) ─────────────────────────── -->
    <div id="view-table" style="display:none">
        <div class="cbr-card overflow-hidden">
            <table class="cbr-table" aria-label="Lista de entidades">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Criado em</th>
                        <?php if ($role === 'coordenador'): ?><th></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entities)): ?>
                    <tr><td colspan="5" class="text-center py-4 text-subtle">Nenhuma entidade cadastrada.</td></tr>
                    <?php else: ?>
                    <?php foreach ($entities as $entity):
                        $ti = $typeIconMap[$entity['type']] ?? $typeIconMap['person'];
                    ?>
                    <tr data-name="<?= esc(mb_strtolower($entity['name'])) ?>"
                        data-type="<?= esc($entity['type']) ?>"
                        data-status="<?= esc($entity['status']) ?>">
                        <td>
                            <a href="<?= base_url('entidades/' . $entity['id']) ?>"
                               class="d-flex align-items-center gap-2 text-decoration-none">
                                <span style="color:<?= $ti['color'] ?>">
                                    <i class="bi <?= $ti['icon'] ?>" aria-hidden="true"></i>
                                </span>
                                <span style="color:var(--cbr-text);font-weight:500"><?= esc($entity['name']) ?></span>
                            </a>
                        </td>
                        <td><span class="badge-type <?= 'badge-' . $ti['css'] ?>"><?= esc($ti['label']) ?></span></td>
                        <td>
                            <span class="<?= $entity['status'] === 'confirmed' ? 'badge-confirmed' : 'badge-hypothesis' ?>">
                                <?= $entity['status'] === 'confirmed' ? 'Confirmado' : 'Hipótese' ?>
                            </span>
                        </td>
                        <td class="text-subtle" style="font-size:.8125rem">
                            <?= isset($entity['created_at']) ? date('d/m/Y', strtotime($entity['created_at'])) : '—' ?>
                        </td>
                        <?php if ($role === 'coordenador'): ?>
                        <td>
                            <?php if ($entity['status'] === 'hypothesis'): ?>
                            <button class="btn-confirm"
                                    data-confirm-entity="<?= $entity['id'] ?>"
                                    data-entity-name="<?= esc($entity['name']) ?>"
                                    data-confirm-url="<?= base_url('entidades/' . $entity['id'] . '/confirmar') ?>">
                                <i class="bi bi-patch-check" aria-hidden="true"></i> Confirmar
                            </button>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div><!-- /.fade-in-up -->

<?php
$content = ob_get_clean();
echo view('layout/base', [
    'title'      => 'Entidades',
    'breadcrumbs'=> [['label' => 'Dashboard', 'url' => base_url('/')], ['label' => 'Entidades', 'url' => '']],
    'content'    => $content,
    'pageCss'    => ['entities.css'],
    'pageJs'     => ['entities.js'],
]);
?>
