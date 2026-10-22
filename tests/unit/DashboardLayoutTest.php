<?php

use CodeIgniter\Test\CIUnitTestCase;

final class DashboardLayoutTest extends CIUnitTestCase
{
    public function testRecentListsStartCollapsedAndPreserveEntityLinks(): void
    {
        // O template existente encerra um buffer inicial; isolar o teste dele.
        ob_start();
        $html = view('dashboard/index', [
            'recentEntities' => [['id' => 7, 'name' => 'Entidade recente', 'type' => 'person', 'status' => 'hypothesis']],
            'recentRelationships' => [],
            'graphData' => ['entities' => [['id' => 7, 'name' => 'Entidade recente', 'type' => 'person', 'status' => 'hypothesis']], 'relationships' => []],
        ]);
        $dom = new DOMDocument();
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($dom);
        $panel = $xpath->query('//*[@id="dashboard-recent-content"]')->item(0);
        $this->assertNotNull($panel);
        $this->assertStringNotContainsString('show', $panel->getAttribute('class'));
        $this->assertSame(1, $xpath->query('//*[@aria-controls="dashboard-recent-content" and @aria-expanded="false"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="dashboard-recent-content"]//a[contains(@href,"entidades/7")]')->length);
        $this->assertStringContainsString('cbr-dashboard-graph', $html);
        $this->assertStringContainsString('dashboard.js', $html);
    }
}
