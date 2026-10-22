<?php

use App\Services\UnresolvedRelationshipService;
use CodeIgniter\Test\CIUnitTestCase;

final class UnresolvedRelationshipTest extends CIUnitTestCase
{
    public function testMatchingNamesUseOnlyCaseAndWhitespaceNormalization(): void
    {
        $map = ['chá do paraná' => 1, 'dr. petinio' => 2];
        $this->assertNull(UnresolvedRelationshipService::reason([
            'source_name' => '  CHÁ  do Paraná ', 'target_name' => 'Dr. Petinio',
        ], $map));
        $this->assertNotNull(UnresolvedRelationshipService::reason([
            'source_name' => 'Cha do Parana', 'target_name' => 'Dr. Petinio',
        ], $map));
        $this->assertNotNull(UnresolvedRelationshipService::reason([], $map));
    }

    public function testSelfRelationshipRemainsForReview(): void
    {
        $this->assertStringContainsString('mesma identidade', UnresolvedRelationshipService::reason([
            'source_name' => 'A', 'target_name' => 'A',
        ], ['a' => 1]));
    }

    public function testPendingViewEscapesOriginalPayload(): void
    {
        $html = view('entities/unresolved_relationships', ['unresolvedRelationships' => [[
            'suggestion' => json_encode(['source_name' => '<script>nome</script>', 'target_name' => 'B', 'excerpt' => '<img src=x>']),
            'reason' => 'Nomes não resolvidos',
        ]]]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('Hipótese não resolvida', $html);
        $this->assertStringContainsString('Nomes não resolvidos', $html);
    }
}
