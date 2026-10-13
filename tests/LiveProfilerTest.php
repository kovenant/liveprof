<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler\Tests;

use Badoo\LiveProfiler\DataPacker;
use Badoo\LiveProfiler\LiveProfiler;
use Badoo\LiveProfiler\SimpleProfiler;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\TestCase;

final class LiveProfilerTest extends TestCase
{
    private const SQLITE_URL = 'sqlite:///:memory:';
    private const DATETIME = '2026-10-12 10:00:00';
    private const PROFILE = ['main()' => ['wt' => 1]];

    /** @var array<mixed> */
    private array $server = [];

    protected function setUp(): void
    {
        $this->server = $_SERVER;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
    }

    public function testLabelIsThePathOfTheRequestUri(): void
    {
        $_SERVER['REQUEST_URI'] = '/test/test?param=value';
        $_SERVER['SCRIPT_NAME'] = 'index.php';

        self::assertSame('/test/test', (new LiveProfiler(self::SQLITE_URL))->getLabel());
    }

    public function testLabelFallsBackToTheScriptName(): void
    {
        unset($_SERVER['REQUEST_URI']);
        $_SERVER['SCRIPT_NAME'] = 'index.php';

        self::assertSame('index.php', (new LiveProfiler(self::SQLITE_URL))->getLabel());
    }

    public function testGetInstanceReturnsTheSameProfiler(): void
    {
        self::assertSame(LiveProfiler::getInstance(), LiveProfiler::getInstance());
    }

    public function testConnectionUrlComesFromTheEnvironmentVariable(): void
    {
        putenv('LIVE_PROFILER_CONNECTION_URL=' . self::SQLITE_URL);

        try {
            self::assertSame(self::SQLITE_URL, (new TestableLiveProfiler())->property('connectionString'));
            self::assertSame('mysql://db/profiler', (new TestableLiveProfiler('mysql://db/profiler'))->property('connectionString'));
        } finally {
            putenv('LIVE_PROFILER_CONNECTION_URL');
        }
    }

    public function testStartWithoutProfilerDoesNothing(): void
    {
        $profiler = (new TestableLiveProfiler(self::SQLITE_URL))->withoutProfiler()->setDivider(1);

        self::assertTrue($profiler->start());
        self::assertFalse($profiler->isEnabled());
        self::assertTrue($profiler->end());
        self::assertSame([], $profiler->saved);
    }

    public function testStartRunsTheProfilerAndEndSavesTheProfile(): void
    {
        $started = false;
        $profiler = $this->profiler(self::PROFILE)
            ->setStartCallback(static function () use (&$started): void {
                $started = true;
            });

        self::assertTrue($profiler->start());
        self::assertTrue($started);
        self::assertTrue($profiler->isEnabled());
        self::assertTrue($profiler->end());
        self::assertFalse($profiler->isEnabled());
        self::assertSame([['app' => 'App', 'label' => 'label', 'datetime' => self::DATETIME, 'data' => self::PROFILE]], $profiler->saved);
        self::assertSame(self::PROFILE, $profiler->getLastProfileData());
    }

    public function testStartWithTheTotalDividerUsesTheAllLabel(): void
    {
        $profiler = $this->profiler(self::PROFILE)->setDivider(\PHP_INT_MAX)->setTotalDivider(1);

        $profiler->start();

        self::assertTrue($profiler->isEnabled());
        self::assertSame('All', $profiler->getLabel());
        $profiler->reset();
    }

    public function testProfilingWithTheAllLabelIsDisabledByDefault(): void
    {
        $profiler = $this->profiler(self::PROFILE)->setDivider(\PHP_INT_MAX);

        self::assertSame(0, $profiler->property('totalDivider'));
        self::assertTrue($profiler->start());
        self::assertFalse($profiler->isEnabled());
        self::assertSame('label', $profiler->getLabel());
    }

    public function testNonPositiveDividersNeverStartProfiling(): void
    {
        $profiler = $this->profiler(self::PROFILE)->setDivider(0)->setTotalDivider(-1);

        self::assertTrue($profiler->start());
        self::assertFalse($profiler->isEnabled());
    }

    public function testEndWithoutStartDoesNothing(): void
    {
        $profiler = $this->profiler(self::PROFILE);

        self::assertTrue($profiler->end());
        self::assertSame([], $profiler->saved);
    }

    public function testEndWithInvalidProfilerDataLogsAWarning(): void
    {
        $logger = new RecordingLogger();
        $profiler = $this->profiler(null)->setLogger($logger);

        $profiler->start();

        self::assertFalse($profiler->end());
        self::assertSame(['Invalid profiler data: NULL'], $logger->messages('warning'));
        self::assertSame([], $profiler->saved);
    }

    public function testEndWithEmptyProfilerDataReturnsFalse(): void
    {
        $profiler = $this->profiler([]);

        $profiler->start();

        self::assertFalse($profiler->end());
        self::assertSame([], $profiler->saved);
    }

    public function testEndLogsAWarningWhenTheProfileIsNotSaved(): void
    {
        $logger = new RecordingLogger();
        $profiler = $this->profiler(self::PROFILE)->setLogger($logger);
        $profiler->saveResult = false;

        $profiler->start();

        self::assertFalse($profiler->end());
        self::assertSame(['Can\'t insert profile data'], $logger->messages('warning'));
    }

    public function testEndLogsAnErrorWhenSavingThrows(): void
    {
        $logger = new RecordingLogger();
        $profiler = $this->realProfiler(new LiveProfiler(self::SQLITE_URL))->setLogger($logger);

        $profiler->start();

        self::assertFalse($profiler->end(), 'The "details" table does not exist');
        self::assertCount(1, $logger->messages('error'));
        self::assertStringStartsWith('Error in saving profile data: ', $logger->messages('error')[0]);
    }

    public function testResetStopsProfilingWithoutSaving(): void
    {
        $ended = false;
        $profiler = $this->profiler(self::PROFILE)
            ->setEndCallback(static function () use (&$ended): array {
                $ended = true;

                return self::PROFILE;
            });

        $profiler->start();

        self::assertTrue($profiler->reset());
        self::assertTrue($ended);
        self::assertFalse($profiler->isEnabled());
        self::assertTrue($profiler->end());
        self::assertSame([], $profiler->saved);
    }

    public function testProfilerCanNotBeChangedAfterStart(): void
    {
        $logger = new RecordingLogger();
        $profiler = $this->profiler(self::PROFILE)->setLogger($logger);
        $profiler->start();
        $startCallback = $profiler->getStartCallback();
        $endCallback = $profiler->getEndCallback();

        foreach (['useXhprof', 'useXhprofSample', 'useTidyWays', 'useSimpleProfiler', 'detectProfiler'] as $method) {
            self::assertSame($profiler, $profiler->{$method}());
        }

        self::assertSame($startCallback, $profiler->getStartCallback());
        self::assertSame($endCallback, $profiler->getEndCallback());
        self::assertCount(5, $logger->messages('warning'));
        $profiler->reset();
    }

    public function testEveryProfilerSetsItsOwnCallbacks(): void
    {
        $profiler = new TestableLiveProfiler(self::SQLITE_URL);
        $callbacks = [];

        foreach (['useXhprof', 'useXhprofSample', 'useTidyWays', 'useSimpleProfiler'] as $method) {
            self::assertSame($profiler, $profiler->{$method}());

            $startCallback = $profiler->getStartCallback();
            self::assertInstanceOf(\Closure::class, $startCallback);
            self::assertInstanceOf(\Closure::class, $profiler->getEndCallback());
            self::assertNotContains($startCallback, $callbacks);
            $callbacks[] = $startCallback;
        }
    }

    public function testDetectProfilerFallsBackToTheSimpleProfiler(): void
    {
        if (\function_exists('xhprof_enable') || \function_exists('tideways_xhprof_enable')) {
            self::markTestSkipped('An xhprof extension is loaded');
        }

        $profiler = (new TestableLiveProfiler(self::SQLITE_URL))->setDivider(1);

        $profiler->start();
        SimpleProfiler::getInstance()->startTimer('tag');
        SimpleProfiler::getInstance()->endTimer('tag');

        self::assertTrue($profiler->end());
        self::assertSame(['main()', 'main()==>tag'], array_keys($profiler->getLastProfileData()));
    }

    public function testSampleDataIsConvertedToTheCommonFormat(): void
    {
        $samples = [
            '1001' => 'main()==>func',
            '1002' => 'main()==>func==>func2',
            '1003' => 'main()',
        ];

        $result = (new TestableLiveProfiler(self::SQLITE_URL))->convertSamples($samples, 1000.0);

        self::assertSame([
            'main()' => ['ct' => 3, 'wt' => 3000000],
            'main()==>func' => ['ct' => 2, 'wt' => 2000000],
            'func==>func2' => ['ct' => 1, 'wt' => 1000000],
        ], $result);
    }

    public function testSettersAndGetters(): void
    {
        $logger = new RecordingLogger();
        $packer = new DataPacker();
        $connection = $this->createStub(Connection::class);
        $closure = static function (): void {};

        $profiler = (new TestableLiveProfiler(self::SQLITE_URL))
            ->setApp('app')
            ->setLabel('label')
            ->setDateTime(self::DATETIME)
            ->setDivider(1)
            ->setTotalDivider(2)
            ->setLogger($logger)
            ->setDataPacker($packer)
            ->setConnection($connection)
            ->setConnectionString('mysql://localhost/db')
            ->setStartCallback($closure)
            ->setEndCallback($closure);

        self::assertSame('app', $profiler->getApp());
        self::assertSame('label', $profiler->getLabel());
        self::assertSame(self::DATETIME, $profiler->getDateTime());
        self::assertSame(1, $profiler->property('divider'));
        self::assertSame(2, $profiler->property('totalDivider'));
        self::assertSame($logger, $profiler->property('logger'));
        self::assertSame($packer, $profiler->property('dataPacker'));
        self::assertSame($connection, $profiler->property('connection'));
        self::assertSame('mysql://localhost/db', $profiler->property('connectionString'));
        self::assertSame($closure, $profiler->getStartCallback());
        self::assertSame($closure, $profiler->getEndCallback());
        self::assertSame([], $profiler->getLastProfileData());
        self::assertSame([], $logger->records);
    }

    public function testProfileIsSavedToTheDatabase(): void
    {
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $profiler = $this->realProfiler(new LiveProfiler())->setConnection($connection);

        self::assertTrue($profiler->createTable());
        $profiler->start();

        self::assertTrue($profiler->end());
        self::assertSame(
            ['app' => 'App', 'label' => 'label', 'timestamp' => self::DATETIME, 'perfdata' => '{"main()":{"wt":1}}'],
            $connection->fetchAssociative('SELECT app, label, timestamp, perfdata FROM details')
        );
    }

    public function testConnectionIsCreatedFromTheConnectionUrl(): void
    {
        $profiler = $this->realProfiler(new LiveProfiler(self::SQLITE_URL));

        self::assertTrue($profiler->createTable());
        $profiler->start();

        self::assertTrue($profiler->end());
    }

    public function testCreateTableRejectsUnsupportedPlatforms(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($this->createStub(AbstractPlatform::class));
        $logger = new RecordingLogger();
        $profiler = (new LiveProfiler())->setConnection($connection)->setLogger($logger);

        self::assertFalse($profiler->createTable());
        self::assertCount(1, $logger->messages('error'));
        self::assertStringStartsWith('Unsupported database platform: ', $logger->messages('error')[0]);
    }

    /**
     * A profiler that records the profiles instead of saving them and always starts.
     *
     * @param array<string, mixed>|null $profile the data returned by the profiler
     */
    private function profiler(?array $profile): TestableLiveProfiler
    {
        return (new TestableLiveProfiler(self::SQLITE_URL))
            ->setApp('App')
            ->setLabel('label')
            ->setDateTime(self::DATETIME)
            ->setDivider(1)
            ->setStartCallback(static function (): void {})
            ->setEndCallback(static fn (): ?array => $profile);
    }

    /**
     * Configures a profiler that always starts and returns a fixed profile.
     */
    private function realProfiler(LiveProfiler $profiler): LiveProfiler
    {
        return $profiler
            ->setApp('App')
            ->setLabel('label')
            ->setDateTime(self::DATETIME)
            ->setDivider(1)
            ->setStartCallback(static function (): void {})
            ->setEndCallback(static fn (): array => self::PROFILE);
    }
}
