<?php

use App\Controllers\EntityController;
use App\Models\EntityModel;
use CodeIgniter\Test\CIUnitTestCase;

final class GraphVisibilityTest extends CIUnitTestCase
{
    public function testVisibleEntityOffersHideWithCsrfAndExplicitState(): void
    {
        $html = view('entities/graph_visibility', ['entity' => ['id' => 42, 'graph_hidden' => 'f']]);
        $this->assertStringContainsString('Ocultar no grafo', $html);
        $this->assertStringContainsString('name="graph_hidden" value="1"', $html);
        $this->assertStringContainsString('method="post"', $html);
        $this->assertStringContainsString('name="' . csrf_token() . '"', $html);
        $this->assertStringContainsString('entidades/42/visibilidade-grafo', $html);
    }

    public function testHiddenEntityOffersRestoreForPostgresBoolean(): void
    {
        $html = view('entities/graph_visibility', ['entity' => ['id' => 42, 'graph_hidden' => 't']]);
        $this->assertStringContainsString('Mostrar no grafo', $html);
        $this->assertStringContainsString('name="graph_hidden" value="0"', $html);
    }

    public function testControllerWritesOnlyRequestedVisibilityAndRejectsInvalidState(): void
    {
        $model = $this->getMockBuilder(EntityModel::class)->disableOriginalConstructor()
            ->onlyMethods(['find', 'setGraphHidden'])->getMock();
        $model->method('find')->with(42)->willReturn(['id' => 42]);
        $model->expects($this->once())->method('setGraphHidden')->with(42, true)->willReturn(true);
        $controller = (new ReflectionClass(EntityController::class))->newInstanceWithoutConstructor();
        (new ReflectionProperty(EntityController::class, 'entityModel'))->setValue($controller, $model);
        $request = service('request');
        $controller->initController($request, service('response'), service('logger'));
        $request->setGlobal('post', ['graph_hidden' => '1']);
        $controller->setGraphVisibility(42);
        $this->assertStringContainsString('oculta', session()->getFlashdata('success'));
        $request->setGlobal('post', ['graph_hidden' => 'invalid']);
        $controller->setGraphVisibility(42);
        $this->assertSame('Opção de visibilidade inválida.', session()->getFlashdata('error'));
    }
}
