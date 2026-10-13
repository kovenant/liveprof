<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler\Tests;

use Badoo\LiveProfiler\Logger;
use PHPUnit\Framework\TestCase;

final class LoggerTest extends TestCase
{
    private string $logfile;

    protected function setUp(): void
    {
        $this->logfile = (string)tempnam(sys_get_temp_dir(), 'liveprof_log');
    }

    protected function tearDown(): void
    {
        @unlink($this->logfile);
    }

    public function testLogAppendsLinesWithDateLevelMessageAndContext(): void
    {
        $logger = new Logger($this->logfile);

        $logger->log('error', 'Error msg', ['param' => 1]);
        $logger->warning('Warning msg');

        self::assertSame(
            \sprintf("%1\$s\terror\tError msg\t{\"param\":1}\n%1\$s\twarning\tWarning msg\n", date('Y-m-d H:i:s')),
            file_get_contents($this->logfile)
        );
    }

    public function testSetLogFileChangesTheFile(): void
    {
        $logger = new Logger('/dev/null');

        self::assertSame($logger, $logger->setLogFile($this->logfile));

        $logger->info('Info msg');

        self::assertStringEndsWith("\tinfo\tInfo msg\n", (string)file_get_contents($this->logfile));
    }

    public function testStringableMessagesAndNonStringLevelsAreConverted(): void
    {
        $message = new class implements \Stringable {
            public function __toString(): string
            {
                return 'Stringable msg';
            }
        };

        (new Logger($this->logfile))->log(500, $message);

        self::assertStringEndsWith("\t500\tStringable msg\n", (string)file_get_contents($this->logfile));
    }
}
