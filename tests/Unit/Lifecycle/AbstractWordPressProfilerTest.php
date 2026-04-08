<?php

namespace Perfbase\WordPress\Tests\Unit\Lifecycle;

use Mockery;
use Perfbase\SDK\Perfbase;
use Perfbase\SDK\SubmitResult;
use Perfbase\WordPress\Lifecycle\AbstractWordPressProfiler;
use Perfbase\WordPress\PerfbasePlugin;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Perfbase\WordPress\Tests\Helpers\MockFactory;
use Perfbase\WordPress\Tests\Helpers\TestData;

/**
 * Concrete test implementation of the abstract profiler.
 */
class ConcreteTestProfiler extends AbstractWordPressProfiler
{
    /** @var bool */
    public bool $shouldProfileResult = true;

    protected function shouldProfile(): bool
    {
        return $this->shouldProfileResult;
    }
}

class AbstractWordPressProfilerTest extends BaseWordPressTest
{
    /** @var Perfbase&\Mockery\MockInterface */
    private $mockPerfbase;

    /** @var PerfbasePlugin&\Mockery\MockInterface */
    private $mockPlugin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockPerfbase = MockFactory::createMockPerfbase();
        $this->mockPlugin = Mockery::mock(PerfbasePlugin::class);
        $this->mockPlugin->shouldReceive('get_perfbase')->andReturn($this->mockPerfbase);
        $this->mockPlugin->shouldReceive('get_config')->andReturn(
            array_merge(TestData::getValidConfig(), ['sample_rate' => 1.0])
        );
    }

    public function testStartProfilingCallsStartTraceSpan(): void
    {
        $this->mockPerfbase->shouldReceive('startTraceSpan')
            ->with('test.span')
            ->once();

        $profiler = new ConcreteTestProfiler('test.span', $this->mockPlugin);
        $profiler->startProfiling();

        $this->addToAssertionCount(1);
    }

    public function testStartProfilingSkipsWhenShouldProfileFalse(): void
    {
        $this->mockPerfbase->shouldNotReceive('startTraceSpan');

        $profiler = new ConcreteTestProfiler('test.span', $this->mockPlugin);
        $profiler->shouldProfileResult = false;
        $profiler->startProfiling();

        $this->addToAssertionCount(1);
    }

    public function testStartProfilingSkipsWhenSampleRateZero(): void
    {
        $plugin = Mockery::mock(PerfbasePlugin::class);
        $plugin->shouldReceive('get_perfbase')->andReturn($this->mockPerfbase);
        $plugin->shouldReceive('get_config')->andReturn(
            array_merge(TestData::getValidConfig(), ['sample_rate' => 0.0])
        );

        $this->mockPerfbase->shouldNotReceive('startTraceSpan');

        $profiler = new ConcreteTestProfiler('test.span', $plugin);
        $profiler->startProfiling();

        $this->addToAssertionCount(1);
    }

    public function testStartProfilingSkipsWhenSdkNull(): void
    {
        $plugin = Mockery::mock(PerfbasePlugin::class);
        $plugin->shouldReceive('get_perfbase')->andReturn(null);
        $plugin->shouldReceive('get_config')->andReturn(TestData::getValidConfig());

        $profiler = new ConcreteTestProfiler('test.span', $plugin);
        $profiler->startProfiling();

        // Should not crash
        $this->assertTrue(true);
    }

    public function testStopProfilingSubmitsAndSucceeds(): void
    {
        $this->mockPerfbase->shouldReceive('stopTraceSpan')->with('test.span')->andReturn(true)->once();
        $this->mockPerfbase->shouldReceive('submitTrace')->andReturn(SubmitResult::success())->once();

        $profiler = new ConcreteTestProfiler('test.span', $this->mockPlugin);
        $profiler->stopProfiling();

        $this->addToAssertionCount(1);
    }

    public function testStopProfilingHandlesSubmitFailure(): void
    {
        $this->mockPerfbase->shouldReceive('stopTraceSpan')->andReturn(true);
        $this->mockPerfbase->shouldReceive('submitTrace')
            ->andReturn(SubmitResult::retryableFailure(503, 'Service Unavailable'));

        $profiler = new ConcreteTestProfiler('test.span', $this->mockPlugin);
        // Should not throw in production mode (debug=false)
        $profiler->stopProfiling();

        $this->assertTrue(true);
    }

    public function testStopProfilingSkipsWhenSpanNotStarted(): void
    {
        $this->mockPerfbase->shouldReceive('stopTraceSpan')->andReturn(false);
        $this->mockPerfbase->shouldNotReceive('submitTrace');

        $profiler = new ConcreteTestProfiler('test.span', $this->mockPlugin);
        $profiler->stopProfiling();

        $this->addToAssertionCount(1);
    }

    public function testStopProfilingSkipsWhenSdkNull(): void
    {
        $plugin = Mockery::mock(PerfbasePlugin::class);
        $plugin->shouldReceive('get_perfbase')->andReturn(null);
        $plugin->shouldReceive('get_config')->andReturn(TestData::getValidConfig());

        $profiler = new ConcreteTestProfiler('test.span', $plugin);
        $profiler->stopProfiling();

        $this->assertTrue(true);
    }

    public function testSetAttributeAccumulates(): void
    {
        $profiler = new ConcreteTestProfiler('test.span', $this->mockPlugin);
        $profiler->setAttribute('key1', 'val1');
        $profiler->setAttribute('key2', 'val2');

        // Attributes are set on SDK during stopProfiling
        $this->mockPerfbase->shouldReceive('setAttribute')->with('key1', 'val1')->once();
        $this->mockPerfbase->shouldReceive('setAttribute')->with('key2', 'val2')->once();
        $this->mockPerfbase->shouldReceive('stopTraceSpan')->andReturn(false);

        $profiler->stopProfiling();
        $this->addToAssertionCount(1);
    }

    public function testSetAttributesBulk(): void
    {
        $profiler = new ConcreteTestProfiler('test.span', $this->mockPlugin);
        $profiler->setAttributes(['a' => 'b', 'c' => 'd']);

        $this->mockPerfbase->shouldReceive('setAttribute')->with('a', 'b')->once();
        $this->mockPerfbase->shouldReceive('setAttribute')->with('c', 'd')->once();
        $this->mockPerfbase->shouldReceive('stopTraceSpan')->andReturn(false);

        $profiler->stopProfiling();
        $this->addToAssertionCount(1);
    }

    public function testGetSpanName(): void
    {
        $profiler = new ConcreteTestProfiler('my.span', $this->mockPlugin);
        $this->assertSame('my.span', $profiler->getSpanName());
    }

    public function testSampleRateOneAlwaysPasses(): void
    {
        $plugin = Mockery::mock(PerfbasePlugin::class);
        $plugin->shouldReceive('get_perfbase')->andReturn($this->mockPerfbase);
        $plugin->shouldReceive('get_config')->andReturn(
            array_merge(TestData::getValidConfig(), ['sample_rate' => 1.0])
        );

        $this->mockPerfbase->shouldReceive('startTraceSpan')->once();

        $profiler = new ConcreteTestProfiler('test.span', $plugin);
        $profiler->startProfiling();
        $this->addToAssertionCount(1);
    }

    public function testInvalidSampleRateSkips(): void
    {
        $plugin = Mockery::mock(PerfbasePlugin::class);
        $plugin->shouldReceive('get_perfbase')->andReturn($this->mockPerfbase);
        $plugin->shouldReceive('get_config')->andReturn(
            array_merge(TestData::getValidConfig(), ['sample_rate' => 'invalid'])
        );

        $this->mockPerfbase->shouldNotReceive('startTraceSpan');

        $profiler = new ConcreteTestProfiler('test.span', $plugin);
        $profiler->startProfiling();
        $this->addToAssertionCount(1);
    }

    public function testDefaultAttributesSetHostnameAndPhpVersion(): void
    {
        $this->mockPerfbase->shouldReceive('startTraceSpan')->once();

        $profiler = new ConcreteTestProfiler('test.span', $this->mockPlugin);
        $profiler->startProfiling();

        // Check attributes were accumulated (they'll be set on SDK during stopProfiling)
        $reflection = new \ReflectionClass($profiler);
        $prop = $reflection->getProperty('attributes');
        $prop->setAccessible(true);
        $attrs = $prop->getValue($profiler);

        $this->assertArrayHasKey('hostname', $attrs);
        $this->assertArrayHasKey('php_version', $attrs);
        $this->assertSame(phpversion() ?: '', $attrs['php_version']);
    }
}
