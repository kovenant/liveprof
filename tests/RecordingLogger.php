<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler\Tests;

use Psr\Log\AbstractLogger;

/**
 * Keeps the logged messages in memory. The message parameter is untyped to fit psr/log 1, 2 and 3.
 */
final class RecordingLogger extends AbstractLogger
{
    /** @var list<array{level: string, message: string}> */
    public array $records = [];

    /**
     * @param string|\Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => \is_string($level) ? $level : get_debug_type($level), 'message' => (string)$message];
    }

    /**
     * @return list<string>
     */
    public function messages(string $level): array
    {
        $messages = [];
        foreach ($this->records as $record) {
            if ($record['level'] === $level) {
                $messages[] = $record['message'];
            }
        }

        return $messages;
    }
}
