'use strict';

document.addEventListener('DOMContentLoaded', function () {
    const raw = document.getElementById('graph-data');
    if (raw && typeof CerebroGraph !== 'undefined') {
        try {
            CerebroGraph.init('cerebro-graph', JSON.parse(raw.textContent));
        } catch (error) {
            console.error('Graph init error:', error);
        }
    }
});
