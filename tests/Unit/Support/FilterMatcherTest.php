<?php

namespace Perfbase\WordPress\Tests\Unit\Support;

use Perfbase\WordPress\Support\FilterMatcher;
use Perfbase\WordPress\Tests\Helpers\BaseWordPressTest;

/**
 * Test FilterMatcher support class
 */
class FilterMatcherTest extends BaseWordPressTest
{
    // ---------------------------------------------------------------
    // matches()
    // ---------------------------------------------------------------

    public function testMatchesWildcardStar()
    {
        $this->assertTrue(FilterMatcher::matches(['/any/path'], ['*']));
    }

    public function testMatchesDotStar()
    {
        $this->assertTrue(FilterMatcher::matches(['/any/path'], ['.*']));
    }

    public function testMatchesExactPath()
    {
        $this->assertTrue(FilterMatcher::matches(['/favicon.ico'], ['/favicon.ico']));
    }

    public function testMatchesGlobPattern()
    {
        $this->assertTrue(FilterMatcher::matches(
            ['/wp-content/uploads/2023/image.jpg'],
            ['/wp-content/uploads/*']
        ));
    }

    public function testDoesNotMatchUnrelatedPath()
    {
        $this->assertFalse(FilterMatcher::matches(
            ['/about-us/'],
            ['/wp-content/uploads/*']
        ));
    }

    public function testMatchesRegexPattern()
    {
        $this->assertTrue(FilterMatcher::matches(
            ['POST /users'],
            ['/^POST \/users/']
        ));
    }

    public function testDoesNotMatchRegexPattern()
    {
        $this->assertFalse(FilterMatcher::matches(
            ['GET /users'],
            ['/^POST \/users/']
        ));
    }

    public function testIgnoresInvalidRegexWithoutWarning()
    {
        $this->assertFalse(FilterMatcher::matches(
            ['GET /users'],
            ['/[broken(/']
        ));
    }

    public function testMatchesMultipleComponents()
    {
        // Second component matches
        $this->assertTrue(FilterMatcher::matches(
            ['/page', 'GET /page'],
            ['GET /page']
        ));
    }

    public function testMatchesEmptyFiltersReturnsFalse()
    {
        $this->assertFalse(FilterMatcher::matches(['/test'], []));
    }

    // ---------------------------------------------------------------
    // passesFilters()
    // ---------------------------------------------------------------

    public function testPassesFiltersIncludeAllExcludeNone()
    {
        $this->assertTrue(FilterMatcher::passesFilters(
            ['/test'],
            ['http' => ['*']],
            ['http' => []],
            'http'
        ));
    }

    public function testPassesFiltersExcludedPath()
    {
        $this->assertFalse(FilterMatcher::passesFilters(
            ['/favicon.ico'],
            ['http' => ['*']],
            ['http' => ['/favicon.ico']],
            'http'
        ));
    }

    public function testPassesFiltersNotIncluded()
    {
        $this->assertFalse(FilterMatcher::passesFilters(
            ['/test'],
            ['http' => ['/other/*']],
            ['http' => []],
            'http'
        ));
    }

    public function testPassesFiltersMissingKeyReturnsFalse()
    {
        // No 'ajax' key in include config
        $this->assertFalse(FilterMatcher::passesFilters(
            ['test_action'],
            ['http' => ['*']],
            ['http' => []],
            'ajax'
        ));
    }

    public function testPassesFiltersAjaxContext()
    {
        $this->assertTrue(FilterMatcher::passesFilters(
            ['heartbeat'],
            ['ajax' => ['*']],
            ['ajax' => []],
            'ajax'
        ));
    }

    public function testPassesFiltersAjaxExcluded()
    {
        $this->assertFalse(FilterMatcher::passesFilters(
            ['heartbeat'],
            ['ajax' => ['*']],
            ['ajax' => ['heartbeat']],
            'ajax'
        ));
    }

    public function testPassesFiltersCronContext()
    {
        $this->assertTrue(FilterMatcher::passesFilters(
            ['cron'],
            ['cron' => ['*']],
            ['cron' => []],
            'cron'
        ));
    }

    public function testPassesFiltersCliContext()
    {
        $this->assertTrue(FilterMatcher::passesFilters(
            ['cache-flush'],
            ['cli' => ['*']],
            ['cli' => []],
            'cli'
        ));
    }

    public function testPassesFiltersGlobExclude()
    {
        $this->assertFalse(FilterMatcher::passesFilters(
            ['/wp-content/uploads/2023/photo.png'],
            ['http' => ['*']],
            ['http' => ['/wp-content/uploads/*']],
            'http'
        ));
    }

    public function testPassesFiltersEmptyIncludeReturnsFalse()
    {
        $this->assertFalse(FilterMatcher::passesFilters(
            ['/test'],
            ['http' => []],
            ['http' => []],
            'http'
        ));
    }
}
