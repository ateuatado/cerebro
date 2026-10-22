const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

// Somente navegador simulado: nenhuma requisição real ou limpeza de banco.
for (const file of ['pending.js', 'batch-ingest.js']) {
    function setup({ confirmed = true, data = { success: true, message: 'Limpeza concluída' } } = {}) {
        let click;
        const requests = [], alerts = [];
        const csrf = { dataset: { name: 'csrf_test_name' }, content: 'original' };
        const button = { addEventListener: (_, handler) => { click = handler; } };
        let reloads = 0;
        const window = { BASE_URL: '/cerebro/', location: { reload: () => { reloads++; } } };
        const document = {
            getElementById: id => id === 'btnClearAllDatabase' ? button : null,
            querySelector: () => csrf,
            querySelectorAll: () => [],
            addEventListener: (_, handler) => { if (file === 'pending.js') handler(); },
        };
        vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js', file), 'utf8'), {
            window, document, FormData, confirm: () => confirmed,
            alert: message => alerts.push(message),
            fetch: async (url, options) => {
                requests.push({ url, options });
                return { headers: { get: () => 'renovado' }, json: async () => data };
            },
        });
        return { run: () => file === 'pending.js' ? click() : window.clearFullDatabase(),
            requests, alerts, csrf, reloads: () => reloads };
    }

    test(`${file}: envia CSRF e recebe token renovado`, async () => {
        const app = setup();
        app.run();
        await new Promise(setImmediate);
        assert.equal(app.requests.length, 1);
        assert.equal(app.requests[0].url, '/cerebro/api/limpar-banco-total');
        assert.equal(app.requests[0].options.method, 'POST');
        assert.equal(app.requests[0].options.body.get('csrf_test_name'), 'original');
        assert.equal(app.csrf.content, 'renovado');
        assert.equal(app.reloads(), 1);
    });

    test(`${file}: cancelar não envia requisição`, () => {
        const app = setup({ confirmed: false });
        app.run();
        assert.equal(app.requests.length, 0);
    });

    test(`${file}: mostra a mensagem real do servidor`, async () => {
        const app = setup({ data: { message: 'The action you requested is not allowed.' } });
        app.run();
        await new Promise(setImmediate);
        assert.match(app.alerts[0], /The action you requested is not allowed/);
        assert.equal(app.reloads(), 0);
    });
}
