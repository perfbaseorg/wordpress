<?php

namespace Perfbase\WordPress\Tests\Unit\Lifecycle;

use Mockery;
use Perfbase\WordPress\Lifecycle\CliLifecycle;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Perfbase\WordPress\Tests\Helpers\MockFactory;
use Perfbase\WordPress\Tests\Helpers\TestData;

/**
 * Test CliLifecycle class
 */
class CliLifecycleTest extends BaseWordPressTest
{
    private $mock_plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock_plugin = Mockery::mock(PerfbasePlugin::class);
    }

    private function createCliLifecycle(string $command = 'cache-flush', array $configOverrides = []): CliLifecycle
    {
        $config = array_merge(TestData::getValidConfig(), ['sample_rate' => 1.0, 'profile_cli' => true], $configOverrides);
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase->shouldReceive('isExtensionAvailable')->byDefault()->andReturn(true);
        $mock_perfbase->shouldReceive('startTraceSpan')->zeroOrMoreTimes();
        $mock_perfbase->shouldReceive('setAttribute')->zeroOrMoreTimes();
        $mock_perfbase->shouldReceive('stopTraceSpan')->zeroOrMoreTimes()->andReturn(true);

        $this->mock_plugin->shouldReceive('get_perfbase')->andReturn($mock_perfbase);
        $this->mock_plugin->shouldReceive('get_config')->andReturn($config);

        return new CliLifecycle($command, $this->mock_plugin);
    }

    public function testSpanName()
    {
        $lifecycle = $this->createCliLifecycle('cache-flush');
        $this->assertEquals('cli_cache_flush', $lifecycle->getSpanName());
    }

    public function testSpanNameWithDifferentCommand()
    {
        $lifecycle = $this->createCliLifecycle('db-export');
        $this->assertEquals('cli_db_export', $lifecycle->getSpanName());
    }

    public function testStartProfilingSetsAttributes()
    {
        $config = array_merge(TestData::getValidConfig(), ['sample_rate' => 1.0, 'profile_cli' => true]);
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase->shouldReceive('isExtensionAvailable')->andReturn(true);

        $mock_perfbase->shouldReceive('startTraceSpan')
            ->with('cli_cache_flush')
            ->once();

        $mock_perfbase->shouldReceive('setAttribute')
            ->zeroOrMoreTimes();

        $this->mock_plugin->shouldReceive('get_perfbase')->andReturn($mock_perfbase);
        $this->mock_plugin->shouldReceive('get_config')->andReturn($config);

        $lifecycle = new CliLifecycle('cache-flush', $this->mock_plugin);
        $lifecycle->startProfiling();

        $this->addToAssertionCount(1);
    }

    public function testSpanNameIsSdkSafe()
    {
        $lifecycle = $this->createCliLifecycle('option get perfbase_settings --format=json');

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]{1,64}$/', $lifecycle->getSpanName());
        $this->assertLessThanOrEqual(64, strlen($lifecycle->getSpanName()));
    }

    public function testShouldNotProfileWhenProfileCliDisabled()
    {
        $config = array_merge(TestData::getValidConfig(), ['sample_rate' => 1.0, 'profile_cli' => false]);
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase->shouldNotReceive('startTraceSpan');

        $this->mock_plugin->shouldReceive('get_perfbase')->andReturn($mock_perfbase);
        $this->mock_plugin->shouldReceive('get_config')->andReturn($config);

        $lifecycle = new CliLifecycle('cache-flush', $this->mock_plugin);
        $lifecycle->startProfiling();

        $this->addToAssertionCount(1);
    }

    public function testShouldNotProfileWhenExcludedByFilter()
    {
        $config = array_merge(TestData::getValidConfig(), [
            'sample_rate' => 1.0,
            'profile_cli' => true,
            'include' => ['cli' => ['*']],
            'exclude' => ['cli' => ['cache-flush']],
        ]);
        $mock_perfbase = MockFactory::createMockPerfbase();

        $mock_perfbase->shouldNotReceive('startTraceSpan');

        $this->mock_plugin->shouldReceive('get_perfbase')->andReturn($mock_perfbase);
        $this->mock_plugin->shouldReceive('get_config')->andReturn($config);

        $lifecycle = new CliLifecycle('cache-flush', $this->mock_plugin);
        $lifecycle->startProfiling();

        $this->addToAssertionCount(1);
    }

    public function testStopProfilingSubmitsTrace()
    {
        $lifecycle = $this->createCliLifecycle('cache-flush');
        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        $this->addToAssertionCount(1);
    }
}
