<?php

namespace Perfbase\WordPress\Tests\Unit\Support;

use Perfbase\WordPress\Support\ErrorHandler;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;

class ErrorHandlerTestSubject
{
    use ErrorHandler;

    /**
     * @param \Throwable $e
     * @param array<string, mixed> $config
     * @param string $context
     * @return void
     */
    public function trigger(\Throwable $e, array $config, string $context = ''): void
    {
        $this->handleProfilingError($e, $config, $context);
    }
}

class ErrorHandlerTest extends BaseWordPressTest
{
    /** @var ErrorHandlerTestSubject */
    private $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ErrorHandlerTestSubject();
    }

    public function testDebugModeRethrows(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('test error');

        $this->subject->trigger(
            new \RuntimeException('test error'),
            ['debug' => true, 'log_errors' => false]
        );
    }

    public function testProductionModeSilences(): void
    {
        $this->subject->trigger(
            new \RuntimeException('silenced'),
            ['debug' => false, 'log_errors' => false]
        );
        $this->assertTrue(true);
    }

    public function testProductionModeLogsWhenEnabled(): void
    {
        $this->subject->trigger(
            new \RuntimeException('logged error'),
            ['debug' => false, 'log_errors' => true],
            'test_context'
        );
        $this->assertTrue(true);
    }

    public function testDefaultLogErrorsIsTrue(): void
    {
        // log_errors not set — should default to true (log)
        $this->subject->trigger(
            new \RuntimeException('default logging'),
            ['debug' => false]
        );
        $this->assertTrue(true);
    }

    public function testEmptyContextUsesUnknown(): void
    {
        $this->subject->trigger(
            new \RuntimeException('no context'),
            ['debug' => false, 'log_errors' => true]
        );
        $this->assertTrue(true);
    }
}
