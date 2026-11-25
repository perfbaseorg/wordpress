<?php

namespace Perfbase\WordPress\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Loads the plugin main file in a fresh PHP process, as WordPress does.
 *
 * See tests/Fixtures/load-plugin-file.php for what the fixture provides.
 */
class PluginFileTest extends TestCase
{
    /**
     * @return array{exitCode: int, output: string}
     */
    private function loadPluginFile(string $mode): array
    {
        $command = sprintf(
            '%s %s %s %s 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__) . '/Fixtures/load-plugin-file.php'),
            escapeshellarg(dirname(__DIR__, 2) . '/perfbase.php'),
            escapeshellarg($mode)
        );

        exec($command, $lines, $exitCode);

        return ['exitCode' => $exitCode, 'output' => implode("\n", $lines) . "\n"];
    }

    public function testInitSurvivesMissingComposerAutoloader()
    {
        if (!is_callable('exec')) {
            $this->markTestSkipped('exec() is not available');
        }

        $result = $this->loadPluginFile('init');

        $this->assertSame(0, $result['exitCode'], $result['output']);
        $this->assertStringNotContainsString('Fatal error', $result['output']);
        $this->assertStringContainsString('Perfbase initialization failed:', $result['output']);
        $this->assertStringContainsString('LOADED', $result['output']);
    }

    public function testActivationSurvivesMissingComposerAutoloader()
    {
        if (!is_callable('exec')) {
            $this->markTestSkipped('exec() is not available');
        }

        $result = $this->loadPluginFile('activate');

        $this->assertSame(0, $result['exitCode'], $result['output']);
        $this->assertStringNotContainsString('Fatal error', $result['output']);
        $this->assertStringContainsString('OPTIONS:[]', $result['output']);
    }
}
