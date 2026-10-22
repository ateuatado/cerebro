const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

test('vínculo documental tem estilo próprio e não aparece entre confirmados', () => {
    let graphData;
    const callbacks = {};
    const buttons = ['all', 'confirmed', 'hypothesis'].map(filter => ({
        dataset: { graphFilter: filter }, classList: { add() {}, remove() {} },
        addEventListener: (_, fn) => { callbacks[filter] = fn; },
    }));
    class DataSet {
        constructor(items) { this.items = items; }
        clear() { this.items = []; }
        add(items) { this.items.push(...items); }
    }
    const context = {
        document: {
            createElement: () => ({}),
            documentElement: { getAttribute: () => 'dark' },
            getElementById: id => id === 'graph' ? {} : null,
            querySelectorAll: () => buttons, addEventListener() {},
        },
        vis: { DataSet, Network: class { constructor(_, data) { graphData = data; } on() {} setOptions() {} } },
        setTimeout() {},
    };
    vm.createContext(context);
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/graph.js'), 'utf8') + '\nglobalThis.graph = CerebroGraph;', context);
    context.graph.init('graph', {
        entities: [{ id: 1, name: 'Pessoa', type: 'person', status: 'confirmed' }, { id: 2, name: 'Fonte', type: 'document', status: 'confirmed' }],
        relationships: [{ id: 'documentary-1-2', source_entity_id: 1, target_entity_id: 2, kind: 'documentary', occurrences: 2 }],
    });
    const edge = graphData.edges.items[0];
    assert.equal(edge.label, 'ocorrência na fonte');
    assert.equal(edge.arrows, '');
    assert.equal(edge._status, 'hypothesis');
    assert.match(edge.title.textContent, /Não representa relação histórica confirmada/);
    assert.equal(edge.label.includes('%'), false);
    callbacks.confirmed();
    assert.equal(graphData.edges.items.length, 0);
    callbacks.all();
    assert.equal(graphData.edges.items.length, 1);
});
