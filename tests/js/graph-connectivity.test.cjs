const { test } = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
const path = require('node:path');

test('destaque acompanha as ligações visíveis sem mudar o estado dos nós', () => {
    let data;
    const callbacks = {};
    const buttons = ['all', 'person'].map(filter => ({
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
            createElement: () => ({}), documentElement: { getAttribute: () => 'dark' },
            getElementById: id => id === 'graph' ? {} : null,
            querySelectorAll: () => buttons, addEventListener() {},
        },
        vis: { DataSet, Network: class { constructor(_, graph) { data = graph; } on() {} setOptions() {} } },
        setTimeout() {},
    };
    vm.createContext(context);
    vm.runInContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/graph.js'), 'utf8') + '\nglobalThis.graph = CerebroGraph;', context);
    context.graph.init('graph', {
        entities: [
            { id: '1', name: 'Pessoa A', type: 'person', status: 'hypothesis' },
            { id: '2', name: 'Local', type: 'location', status: 'confirmed' },
            { id: '3', name: 'Pessoa B', type: 'person', status: 'hypothesis' },
        ],
        relationships: [{ id: 1, source_entity_id: 1, target_entity_id: 2, relationship_type: 'visitou', status: 'hypothesis' }],
    });
    const nodes = () => data.nodes.items;
    assert.equal(nodes()[0].color.background, '#22d3ee');
    assert.equal(nodes()[1].color.background, '#22d3ee');
    assert.equal(nodes()[2].color.background, '#334155');
    assert.equal(nodes()[0].color.border, '#f59e0b');
    assert.equal(nodes()[0]._status, 'hypothesis');
    assert.equal(nodes()[1]._status, 'confirmed');
    const originalSize = nodes()[0].size;
    callbacks.person();
    assert.equal(data.edges.items.length, 0);
    assert.ok(nodes().every(node => node.color.background === '#334155'));
    callbacks.all();
    assert.equal(nodes()[0].color.background, '#22d3ee');
    assert.equal(nodes()[0].size, originalSize);
    assert.equal(data.edges.items.length, 1);
});
