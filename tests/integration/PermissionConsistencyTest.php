<?php

namespace yellowrobot\craftdispatch\tests\integration;

use PHPUnit\Framework\TestCase;

/**
 * Tests that permission checks are consistently applied across all entry points.
 * The canDelete permission gap and missing requireCpRequest were caught in audits —
 * this test ensures they can never regress.
 */
class PermissionConsistencyTest extends TestCase
{
    // ─── Controller permission gates ─────────────────────────

    public function testTemplatesControllerHasRequireCpRequest(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/TemplatesController.php');
        $this->assertStringContainsString('requireCpRequest()', $source, 'TemplatesController must call requireCpRequest');
    }

    public function testLogsControllerHasRequireCpRequest(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/LogsController.php');
        $this->assertStringContainsString('requireCpRequest()', $source, 'LogsController must call requireCpRequest');
    }

    public function testTemplatesControllerActionsRequirePermission(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/TemplatesController.php');

        // Count permission checks — should match the number of action methods
        $permCount = substr_count($source, "requirePermission('craft-dispatch:manage')");
        $actionCount = substr_count($source, 'public function action');

        $this->assertSame(
            $actionCount,
            $permCount,
            "Every action method in TemplatesController must call requirePermission (found {$permCount} checks for {$actionCount} actions)"
        );
    }

    public function testLogsControllerActionsRequirePermission(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/controllers/LogsController.php');

        $permCount = substr_count($source, "requirePermission('craft-dispatch:manage')");
        $actionCount = substr_count($source, 'public function action');

        $this->assertSame(
            $actionCount,
            $permCount,
            "Every action method in LogsController must call requirePermission (found {$permCount} checks for {$actionCount} actions)"
        );
    }

    // ─── Element permission methods ──────────────────────────

    public function testEmailTemplateCanViewChecksPermission(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/elements/EmailTemplate.php');

        // Extract canView method body
        $canViewBody = $this->extractMethodBody($source, 'canView');
        $this->assertStringContainsString(
            "craft-dispatch:manage",
            $canViewBody,
            'canView must check craft-dispatch:manage permission'
        );
    }

    public function testEmailTemplateCanSaveChecksPermission(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/elements/EmailTemplate.php');

        $canSaveBody = $this->extractMethodBody($source, 'canSave');
        $this->assertStringContainsString(
            "craft-dispatch:manage",
            $canSaveBody,
            'canSave must check craft-dispatch:manage permission'
        );
    }

    public function testEmailTemplateCanDeleteChecksPermission(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/elements/EmailTemplate.php');

        $canDeleteBody = $this->extractMethodBody($source, 'canDelete');
        $this->assertStringContainsString(
            "craft-dispatch:manage",
            $canDeleteBody,
            'canDelete must check craft-dispatch:manage permission'
        );
    }

    // ─── Permission registration ─────────────────────────────

    public function testPluginRegistersPermission(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/CraftDispatch.php');

        $this->assertStringContainsString('craft-dispatch:manage', $source, 'Plugin must register the craft-dispatch:manage permission');
        $this->assertStringContainsString('EVENT_REGISTER_PERMISSIONS', $source, 'Plugin must use EVENT_REGISTER_PERMISSIONS');
    }

    // ─── Iframe sandbox ──────────────────────────────────────

    public function testPreviewIframeDoesNotAllowScripts(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/src/templates/templates/_edit.twig');

        // The iframe should have sandbox but NOT allow-scripts
        $this->assertStringContainsString('sandbox="allow-same-origin"', $source, 'Preview iframe must be sandboxed with allow-same-origin');
        $this->assertStringNotContainsString('allow-scripts', $source, 'Preview iframe must NOT allow scripts');
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function extractMethodBody(string $source, string $methodName): string
    {
        $pattern = '/function\s+' . preg_quote($methodName) . '\s*\([^)]*\)[^{]*\{(.*?)\n\s{4}\}/s';
        if (preg_match($pattern, $source, $matches)) {
            return $matches[1];
        }
        return '';
    }
}
