<?php

namespace Perfbase\WordPress\Tests\Unit\Plugin;

use Brain\Monkey\Functions;
use Perfbase\WordPress\Helpers\SamplingStrategy;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;
use Perfbase\WordPress\Tests\Helpers\TestData;

/**
 * Test SamplingStrategy class
 */
class SamplingStrategyTest extends BaseWordPressTest
{
    private $sampling_strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sampling_strategy = new SamplingStrategy();
    }

    public function testShouldSampleAlways()
    {
        $result = $this->sampling_strategy->shouldSample(1.0);
        $this->assertTrue($result);
    }

    public function testShouldSampleNever()
    {
        $result = $this->sampling_strategy->shouldSample(0.0);
        $this->assertFalse($result);
    }

    public function testShouldSampleAboveOne()
    {
        $result = $this->sampling_strategy->shouldSample(1.5);
        $this->assertTrue($result);
    }

    public function testShouldSampleBelowZero()
    {
        $result = $this->sampling_strategy->shouldSample(-0.1);
        $this->assertFalse($result);
    }

    public function testShouldSampleStatisticalDistribution()
    {
        $sample_rate = 0.5;
        $iterations = 1000;
        $sampled_count = 0;

        // Run many iterations to test statistical distribution
        for ($i = 0; $i < $iterations; $i++) {
            if ($this->sampling_strategy->shouldSample($sample_rate)) {
                $sampled_count++;
            }
        }

        $actual_rate = $sampled_count / $iterations;
        $tolerance = 0.1; // 10% tolerance

        $this->assertGreaterThan($sample_rate - $tolerance, $actual_rate);
        $this->assertLessThan($sample_rate + $tolerance, $actual_rate);
    }

    public function testGetSamplingDecisionBasic()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;

        $result = $this->sampling_strategy->getSamplingDecision($config);

        $this->assertTrue($result);
    }

    public function testGetSamplingDecisionWithZeroRate()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 0.0;

        $result = $this->sampling_strategy->getSamplingDecision($config);

        $this->assertFalse($result);
    }

    public function testGetSamplingDecisionWithContext()
    {
        $config = TestData::getValidConfig();
        $config['sample_rate'] = 1.0;

        $context = [
            'user_role' => 'administrator',
            'request_type' => 'frontend'
        ];

        $result = $this->sampling_strategy->getSamplingDecision($config, $context);

        $this->assertTrue($result);
    }

    public function testGetAdaptiveSampleRate()
    {
        $base_rate = 0.1;
        $system_metrics = [
            'memory_usage' => 0.7,
            'cpu_load' => 0.5
        ];

        $result = $this->sampling_strategy->getAdaptiveSampleRate($base_rate, $system_metrics);

        // For now, should return the base rate as adaptive logic is not implemented
        $this->assertEquals($base_rate, $result);
    }

    public function testShouldForceSampleWithDebugParameter()
    {
        $_GET['perfbase_debug'] = '1';

        Functions\when('current_user_can')
            ->justReturn(true);

        $result = $this->sampling_strategy->shouldForceSample();

        $this->assertTrue($result);
    }

    public function testShouldForceSampleWithDebugParameterNonAdmin()
    {
        $_GET['perfbase_debug'] = '1';

        Functions\when('current_user_can')
            ->justReturn(false);

        $result = $this->sampling_strategy->shouldForceSample();

        $this->assertFalse($result);
    }

    public function testShouldForceSampleWithoutDebugParameter()
    {
        unset($_GET['perfbase_debug']);

        Functions\when('current_user_can')
            ->justReturn(true);

        $result = $this->sampling_strategy->shouldForceSample();

        $this->assertFalse($result);
    }

    public function testShouldForceSampleWithContext()
    {
        unset($_GET['perfbase_debug']);

        $context = [
            'force_sample' => true,
            'user_role' => 'administrator'
        ];

        $result = $this->sampling_strategy->shouldForceSample($context);

        $this->assertFalse($result); // Should not force sample based on context alone for now
    }

    /**
     * Test sampling rates across different boundary values
     *
     * @dataProvider samplingRateProvider
     */
    public function testSamplingRateBoundaries($rate, $expected)
    {
        $result = $this->sampling_strategy->shouldSample($rate);

        if ($expected === null) {
            // For random cases, just check that result is boolean
            $this->assertIsBool($result);
        } else {
            $this->assertEquals($expected, $result);
        }
    }

    /**
     * Data provider for sampling rate boundary tests
     */
    public function samplingRateProvider()
    {
        return [
            'zero rate' => [0.0, false],
            'full rate' => [1.0, true],
            'negative rate' => [-0.5, false],
            'above full rate' => [1.5, true],
            'very small positive' => [0.0001, null], // Will be random
            'very close to full' => [0.9999, null], // Will be random
        ];
    }

    public function testShouldSampleConsistency()
    {
        // Test that the same rate produces consistent results over multiple calls
        $rate = 0.0;
        $results = [];

        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->sampling_strategy->shouldSample($rate);
        }

        // All results should be false for 0.0 rate
        $this->assertContainsOnly('boolean', $results);
        $this->assertNotContains(true, $results);

        // Test for 1.0 rate
        $rate = 1.0;
        $results = [];

        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->sampling_strategy->shouldSample($rate);
        }

        // All results should be true for 1.0 rate
        $this->assertContainsOnly('boolean', $results);
        $this->assertNotContains(false, $results);
    }

    public function testSamplingWithVeryLowRate()
    {
        $rate = 0.001; // 0.1%
        $iterations = 10000;
        $sampled_count = 0;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->sampling_strategy->shouldSample($rate)) {
                $sampled_count++;
            }
        }

        $actual_rate = $sampled_count / $iterations;

        // With such a low rate, we expect very few samples
        // Allow for statistical variation, but should be close to 0.1%
        $this->assertLessThan(0.005, $actual_rate); // Less than 0.5%
        $this->assertGreaterThanOrEqual(0, $actual_rate); // At least 0
    }

    public function testSamplingWithHighRate()
    {
        $rate = 0.9; // 90%
        $iterations = 1000;
        $sampled_count = 0;

        for ($i = 0; $i < $iterations; $i++) {
            if ($this->sampling_strategy->shouldSample($rate)) {
                $sampled_count++;
            }
        }

        $actual_rate = $sampled_count / $iterations;

        // Should be close to 90% with some tolerance
        $this->assertGreaterThan(0.8, $actual_rate); // At least 80%
        $this->assertLessThan(1.0, $actual_rate); // Less than 100%
    }
}