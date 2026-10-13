<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

/**
 * Default logger of the profiler: appends the messages to a file, live.profiler.log in the package directory by default.
 */
class Logger implements LoggerInterface
{
    use LoggerTrait;

    private string $logfile;

    public function __construct(?string $logfile = null)
    {
        $this->logfile = $logfile ?? \dirname(__DIR__) . '/live.profiler.log';
    }

    public function setLogFile(string $logfile): static
    {
        $this->logfile = $logfile;

        return $this;
    }

    /**
     * The message parameter is untyped on purpose: psr/log 1 and 2 declare it without a type and psr/log 3
     * as string|\Stringable, and a narrower type here would be incompatible with the older versions.
     *
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $level = \is_scalar($level) ? (string)$level : get_debug_type($level);

        file_put_contents($this->logfile, $this->formatMessage($level, (string)$message, $context), \FILE_APPEND);
    }

    /**
     * @param array<string, mixed> $context
     */
    protected function formatMessage(string $level, string $message, array $context): string
    {
        $line = \sprintf("%s\t%s\t%s", date('Y-m-d H:i:s'), $level, $message);

        if ([] !== $context) {
            $line .= "\t" . json_encode($context, \JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        return $line . "\n";
    }
}
