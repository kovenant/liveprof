<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler\Tests;

use Badoo\LiveProfiler\SimpleProfiler;
use PHPUnit\Framework\TestCase;

final class SimpleProfilerTest extends TestCase
{
    public function testGetInstanceReturnsTheSameProfiler(): void
    {
        self::assertSame(SimpleProfiler::getInstance(), SimpleProfiler::getInstance());
    }

    public function testRunWithoutTagsReportsTheRootOnly(): void
    {
        $profiler = SimpleProfiler::getInstance();
        $profiler->enable();
        usleep(1000);
        $data = $profiler->disable();

        self::assertSame(['main()'], array_keys($data));
        self::assertSame(['ct', 'wt', 'cpu', 'mu'], array_keys($data['main()']));
        self::assertSame(1, $data['main()']['ct']);
        self::assertGreaterThanOrEqual(1000, $data['main()']['wt']);
    }

    public function testNestedTagsAreReportedAsCallerCalleePairs(): void
    {
        $profiler = SimpleProfiler::getInstance();
        $profiler->enable();
        self::assertTrue($profiler->startTimer('outer'));
        self::assertTrue($profiler->startTimer('inner'));
        self::assertTrue($profiler->endTimer('inner'));
        self::assertTrue($profiler->endTimer('outer'));
        self::assertTrue($profiler->startTimer('outer'));
        self::assertTrue($profiler->endTimer('outer'));
        $data = $profiler->disable();

        self::assertSame(['main()', 'main()==>outer', 'outer==>inner'], array_keys($data));
        self::assertSame(2, $data['main()==>outer']['ct']);
        self::assertSame(1, $data['outer==>inner']['ct']);
        self::assertGreaterThanOrEqual($data['outer==>inner']['wt'], $data['main()==>outer']['wt']);
    }

    public function testCpuTimeOfABusySectionIsCollected(): void
    {
        $profiler = SimpleProfiler::getInstance();
        $profiler->enable();
        $profiler->startTimer('busy');
        $until = hrtime(true) + 20_000_000;
        $i = 0;
        while (hrtime(true) < $until) {
            ++$i;
        }
        $profiler->endTimer('busy');
        $data = $profiler->disable();

        self::assertGreaterThan(0, $data['main()==>busy']['cpu']);
        self::assertLessThanOrEqual($data['main()==>busy']['wt'] + 10000, $data['main()==>busy']['cpu'], 'CPU time of a single thread can not exceed the wall time by more than the timer resolution');
    }

    public function testEnableDiscardsThePreviousRun(): void
    {
        $profiler = SimpleProfiler::getInstance();
        $profiler->enable();
        $profiler->startTimer('tag');
        $profiler->endTimer('tag');
        $profiler->disable();

        $profiler->enable();
        $data = $profiler->disable();

        self::assertSame(['main()'], array_keys($data));
        self::assertSame(1, $data['main()']['ct']);
    }

    public function testNotClosedTagProducesNoData(): void
    {
        $profiler = SimpleProfiler::getInstance();
        $profiler->enable();
        $profiler->startTimer('tag');

        self::assertSame([], $profiler->disable());
        self::assertFalse($profiler->startTimer('tag'), 'The profiler is disabled after disable()');
    }

    public function testClosingAnotherTagFails(): void
    {
        $profiler = SimpleProfiler::getInstance();
        $profiler->enable();
        $profiler->startTimer('tag');

        self::assertFalse($profiler->endTimer('invalid'));
        self::assertSame([], $profiler->disable());
    }

    public function testTimersAreIgnoredWhenTheProfilerIsDisabled(): void
    {
        $profiler = SimpleProfiler::getInstance();
        $profiler->enable();
        $profiler->disable();

        self::assertFalse($profiler->startTimer('tag'));
        self::assertFalse($profiler->endTimer('tag'));
    }
}
