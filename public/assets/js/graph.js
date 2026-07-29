/* Cerebro — graph.js — Visualizador de grafo via vis-network */
/* Assets locais, sem inline, sem CDN — AGENTS.md */

'use strict';

const CerebroGraph = (() => {
    let network = null;
    let allNodes = [], allEdges = [];
    let activeFilter = 'all';
    let nodeClickCb = null;

    // Cores por tipo de entidade (espelham as variáveis CSS)
    const TYPE_COLORS = {
        person:   { color: '#60a5fa', highlight: '#93c5fd', border: '#3b82f6', bg: 'rgba(96,165,250,0.15)' },
        location: { color: '#34d399', highlight: '#6ee7b7', border: '#10b981', bg: 'rgba(52,211,153,0.15)' },
        event:    { color: '#f472b6', highlight: '#f9a8d4', border: '#ec4899', bg: 'rgba(244,114,182,0.15)' },
        document: { color: '#fb923c', highlight: '#fdb67a', border: '#f97316', bg: 'rgba(251,146,60,0.15)' },
    };

    const TYPE_ICONS = {
        person:   '👤',
        location: '📍',
        event:    '📅',
        document: '📄',
    };

    function buildNodeOptions(entity) {
        const c = TYPE_COLORS[entity.type] || TYPE_COLORS.person;
        return {
            id:    entity.id,
            label: entity.name.length > 22 ? entity.name.substring(0, 20) + '…' : entity.name,
            title: `<b>${entity.name}</b><br><small>${entity.type}</small><br><span>${entity.status === 'confirmed' ? '✅ Confirmado' : '🟡 Hipótese'}</span>`,
            shape: 'dot',
            size:  entity.status === 'confirmed' ? 18 : 12,
            color: {
                background: c.bg,
                border:     entity.status === 'confirmed' ? c.border : '#f59e0b',
                highlight:  { background: c.highlight, border: c.border },
                hover:      { background: c.highlight, border: c.border },
            },
            font: {
                color: '#e2e8f0',
                size:  13,
                face:  'Inter, system-ui, sans-serif',
            },
            borderWidth:          entity.status === 'confirmed' ? 2 : 1.5,
            borderWidthSelected:  3,
            shadow: {
                enabled: true,
                color:   'rgba(0,0,0,0.4)',
                size:    6,
                x:       0, y:       2,
            },
            group: entity.type,
            // Dados originais para filtro
            _type:   entity.type,
            _status: entity.status,
        };
    }

    function buildEdgeOptions(rel) {
        const isHypothesis = rel.status !== 'confirmed';
        const confidence   = Math.round((rel.confidence || 0.75) * 100);
        return {
            id:    'e' + rel.id,
            from:  rel.source_entity_id,
            to:    rel.target_entity_id,
            label: confidence < 100 ? rel.relationship_type + ' (' + confidence + '%)' : rel.relationship_type,
            title: `<b>${rel.relationship_type}</b><br>Confiança: ${confidence}%<br>${isHypothesis ? '🟡 Hipótese' : '✅ Confirmada'}`,
            arrows: rel.direction === 'directed' ? 'to' : '',
            dashes: isHypothesis,
            color: {
                color:     isHypothesis ? '#f59e0b' : '#7c6af7',
                highlight: '#9585ff',
                hover:     '#9585ff',
                opacity:   isHypothesis ? 0.6 : 0.85,
            },
            width:       isHypothesis ? 1 : 1.5,
            font: {
                color:       '#7a8099',
                size:        11,
                face:        'Inter, system-ui, sans-serif',
                align:       'middle',
                strokeWidth: 3,
                strokeColor: '#1a1d2e',
            },
            smooth: { type: 'curvedCW', roundness: 0.15 },
            _status: rel.status,
        };
    }

    function getOptions(theme) {
        const isDark = theme !== 'light';
        return {
            nodes: {
                scaling: { min: 10, max: 30 },
            },
            edges: {
                scaling: { min: 1, max: 3 },
            },
            physics: {
                enabled: true,
                barnesHut: {
                    gravitationalConstant: -3000,
                    centralGravity:        0.3,
                    springLength:          140,
                    springConstant:        0.04,
                    damping:               0.09,
                },
                stabilization: { iterations: 200, updateInterval: 25 },
            },
            interaction: {
                hover:             true,
                tooltipDelay:      150,
                navigationButtons: false,
                keyboard:          true,
                zoomView:          true,
                dragView:          true,
            },
            layout: { improvedLayout: true },
        };
    }

    function init(containerId, data) {
        const container = document.getElementById(containerId);
        if (!container) return;

        // Detectar tema atual
        const theme = document.documentElement.getAttribute('data-theme') || 'dark';

        allNodes = (data.entities || []).map(buildNodeOptions);
        allEdges = (data.relationships || []).map(buildEdgeOptions);

        const nodesDS = new vis.DataSet(allNodes);
        const edgesDS = new vis.DataSet(allEdges);

        network = new vis.Network(container, { nodes: nodesDS, edges: edgesDS }, getOptions(theme));

        // Navegação ao clicar em nó
        network.on('doubleClick', params => {
            if (params.nodes.length > 0) {
                const nodeId = params.nodes[0];
                const baseUrl = document.querySelector('[data-graph-base-url]')?.dataset.graphBaseUrl || '';
                if (baseUrl) window.location.href = baseUrl + '/entidades/' + nodeId;
            }
        });

        // Single-click: dispara callback externo (painel de detalhe)
        network.on('click', params => {
            if (params.nodes.length > 0 && typeof nodeClickCb === 'function') {
                nodeClickCb(params.nodes[0]);
            }
        });

        // Estabilização
        network.on('stabilizationIterationsDone', () => {
            network.setOptions({ physics: { enabled: false } });
        });

        // Atualizar ao trocar tema
        document.addEventListener('click', e => {
            if (e.target.closest('#theme-toggle')) {
                setTimeout(() => {
                    const t = document.documentElement.getAttribute('data-theme') || 'dark';
                    network.setOptions(getOptions(t));
                }, 100);
            }
        });

        initFilterButtons(nodesDS, edgesDS);
        initGraphControls();
    }

    function initFilterButtons(nodesDS, edgesDS) {
        document.querySelectorAll('[data-graph-filter]').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('[data-graph-filter]').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                activeFilter = btn.dataset.graphFilter;
                applyFilter(nodesDS, edgesDS);
            });
        });
    }

    function applyFilter(nodesDS, edgesDS) {
        const filteredNodes = allNodes.filter(n => {
            if (activeFilter === 'all') return true;
            if (activeFilter === 'confirmed') return n._status === 'confirmed';
            if (activeFilter === 'hypothesis') return n._status === 'hypothesis';
            return n._type === activeFilter;
        });
        const visibleIds = new Set(filteredNodes.map(n => n.id));
        const filteredEdges = allEdges.filter(e => visibleIds.has(e.from) && visibleIds.has(e.to));

        nodesDS.clear();
        edgesDS.clear();
        nodesDS.add(filteredNodes);
        edgesDS.add(filteredEdges);
        network.setOptions({ physics: { enabled: true } });
        setTimeout(() => network.setOptions({ physics: { enabled: false } }), 2000);
    }

    function initGraphControls() {
        document.getElementById('graph-fit')?.addEventListener('click', () => {
            network?.fit({ animation: { duration: 500, easingFunction: 'easeInOutQuad' } });
        });
        document.getElementById('graph-zoom-in')?.addEventListener('click', () => {
            const scale = network.getScale() * 1.3;
            network.moveTo({ scale, animation: { duration: 300 } });
        });
        document.getElementById('graph-zoom-out')?.addEventListener('click', () => {
            const scale = network.getScale() / 1.3;
            network.moveTo({ scale, animation: { duration: 300 } });
        });
        document.getElementById('graph-physics')?.addEventListener('click', function() {
            const enabled = this.classList.toggle('active');
            network?.setOptions({ physics: { enabled } });
        });
    }

    function onNodeClick(cb) {
        nodeClickCb = cb;
    }

    return { init, onNodeClick };
})();
