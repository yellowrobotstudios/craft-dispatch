<?php

namespace yellowrobot\craftdispatch\tests\integration;

use PHPUnit\Framework\TestCase;
use yellowrobot\craftdispatch\migrations\Install;

/**
 * Tests the migration schema definitions to catch missing columns.
 */
class MigrationSchemaTest extends TestCase
{
    private string $migrationSource;

    protected function setUp(): void
    {
        $this->migrationSource = file_get_contents(
            dirname(__DIR__, 2) . '/src/migrations/Install.php'
        );
    }

    // ─── craftdispatch_templates table ───────────────────────

    public function testTemplatesTableHasRequiredColumns(): void
    {
        $required = ['id', 'handle', 'subject', 'htmlBody', 'textBody', 'dateCreated', 'dateUpdated', 'uid'];

        foreach ($required as $column) {
            $this->assertStringContainsString(
                "'{$column}'",
                $this->migrationSource,
                "Templates table missing column: {$column}"
            );
        }
    }

    public function testTemplatesTableHasUniqueHandleIndex(): void
    {
        // The createIndex call with `true` as 4th arg means unique
        $this->assertMatchesRegularExpression(
            '/createIndex\s*\(\s*null\s*,\s*[\'"].*craftdispatch_templates.*[\'"]\s*,\s*\[.*handle.*\]\s*,\s*true\s*\)/',
            $this->migrationSource,
            'Templates table should have a unique index on handle'
        );
    }

    public function testTemplatesTableHasForeignKeyToElements(): void
    {
        $this->assertStringContainsString('addForeignKey', $this->migrationSource);
        $this->assertStringContainsString('elements', $this->migrationSource);
        $this->assertStringContainsString('CASCADE', $this->migrationSource);
    }

    // ─── craftdispatch_logs table ────────────────────────────

    public function testLogsTableHasRequiredColumns(): void
    {
        $required = ['id', 'templateHandle', 'recipient', 'subject', 'status', 'errorMessage', 'dateSent', 'dateCreated', 'dateUpdated', 'uid'];

        foreach ($required as $column) {
            $this->assertStringContainsString(
                "'{$column}'",
                $this->migrationSource,
                "Logs table missing column: {$column}"
            );
        }
    }

    public function testLogsTableHasIndexOnTemplateHandle(): void
    {
        $this->assertStringContainsString("['templateHandle']", $this->migrationSource);
    }

    public function testLogsTableHasIndexOnDateSent(): void
    {
        $this->assertStringContainsString("['dateSent']", $this->migrationSource);
    }

    public function testLogsTableHasIndexOnStatus(): void
    {
        $this->assertStringContainsString("['status']", $this->migrationSource);
    }

    public function testLogsRecipientColumnIsText(): void
    {
        // After the truncation fix, recipient should be text(), not string(255)
        // Look for the recipient line in context of the logs table
        $logsStart = strpos($this->migrationSource, 'craftdispatch_logs');
        $this->assertNotFalse($logsStart, 'Logs table definition must exist');
        $logsSection = substr($this->migrationSource, $logsStart, 500);
        $this->assertMatchesRegularExpression(
            '/recipient.*text\(\)/',
            $logsSection,
            'Logs recipient column should be text() to handle list-mode sends'
        );
    }

    // ─── safeDown ────────────────────────────────────────────

    public function testSafeDownDropsTablesInCorrectOrder(): void
    {
        // Logs must be dropped before templates (templates has FK to elements)
        $logsDropPos = strpos($this->migrationSource, 'craftdispatch_logs');
        $templatesDropPos = strrpos($this->migrationSource, 'craftdispatch_templates');

        // In safeDown, logs drop should come before templates drop
        // Find positions within the safeDown method
        $safeDownPos = strpos($this->migrationSource, 'function safeDown');
        $this->assertNotFalse($safeDownPos);

        $safeDownSection = substr($this->migrationSource, $safeDownPos);
        $logsInDown = strpos($safeDownSection, 'craftdispatch_logs');
        $templatesInDown = strpos($safeDownSection, 'craftdispatch_templates');

        $this->assertLessThan($templatesInDown, $logsInDown, 'Logs table should be dropped before templates table');
    }

    // ─── Idempotency ─────────────────────────────────────────

    public function testBothTablesHaveIndependentExistenceChecks(): void
    {
        // Count occurrences of tableExists — should be at least 2 (one per table)
        $count = substr_count($this->migrationSource, 'tableExists');
        $this->assertGreaterThanOrEqual(2, $count, 'Both tables should have independent existence checks');
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function extractTableSection(string $tableName): string
    {
        $pattern = "/createTable\s*\(\s*['\"].*{$tableName}.*['\"]\s*,\s*\[(.*?)\]\s*\)/s";
        if (preg_match($pattern, $this->migrationSource, $matches)) {
            return $matches[1];
        }
        return '';
    }
}
