<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler;

/**
 * Built-in profiler for servers without the xhprof or tideways_xhprof extension: the profiled code
 * marks its important sections with startTimer()/endTimer() and the profiler collects the number of calls (ct),
 * the wall time (wt), the CPU time (cpu) and the memory usage (mu) of every section in the xhprof format.
 */
class SimpleProfiler
{
    public const ROOT = 'main()';

    private static ?self $instance = null;

    /** @var array<string, array{ct: int, wt: int, cpu: int, mu: int}> "caller==>callee" => counters */
    private array $methods = [];

    /** @var list<array{tag: string, time: int, cpu: int, memory: int}> open timers, the innermost last */
    private array $stack = [];

    private bool $isEnabled = false;

    public static function getInstance(): self
    {
        // @phpstan-ignore new.static (a subclass with a constructor has to override getInstance())
        return self::$instance ??= new static();
    }

    /**
     * Starts a new profile; the data of the previous run is discarded.
     */
    public function enable(): void
    {
        $this->isEnabled = true;
        $this->methods = [];
        $this->stack = [];

        $this->startTimer(self::ROOT);
    }

    /**
     * Stops profiling and returns the collected data, or an empty array when a timer was not closed.
     *
     * @return array<string, array{ct: int, wt: int, cpu: int, mu: int}>
     */
    public function disable(): array
    {
        $this->endTimer(self::ROOT);
        $this->isEnabled = false;

        $isComplete = [] === $this->stack;
        $this->stack = [];

        return $isComplete ? $this->methods : [];
    }

    /**
     * Opens a timer; the tag is reported as the callee of the enclosing timer.
     */
    public function startTimer(string $tag): bool
    {
        if (!$this->isEnabled) {
            return false;
        }

        $this->methods[$this->getFullMethod($tag)] ??= ['ct' => 0, 'wt' => 0, 'cpu' => 0, 'mu' => 0];
        $this->stack[] = ['tag' => $tag, 'time' => self::now(), 'cpu' => self::cpuTime(), 'memory' => memory_get_usage()];

        return true;
    }

    /**
     * Closes the innermost timer; fails when the tag doesn't match it.
     */
    public function endTimer(string $tag): bool
    {
        if (!$this->isEnabled || [] === $this->stack || $this->stack[array_key_last($this->stack)]['tag'] !== $tag) {
            return false;
        }

        $timer = array_pop($this->stack);
        $method = &$this->methods[$this->getFullMethod($tag)];
        ++$method['ct'];
        $method['wt'] += self::now() - $timer['time'];
        $method['cpu'] += self::cpuTime() - $timer['cpu'];
        $method['mu'] += memory_get_usage() - $timer['memory'];

        return true;
    }

    private function getFullMethod(string $tag): string
    {
        if ([] === $this->stack) {
            return $tag;
        }

        return $this->stack[array_key_last($this->stack)]['tag'] . '==>' . $tag;
    }

    /**
     * Monotonic time in microseconds.
     */
    private static function now(): int
    {
        return intdiv(hrtime(true), 1000);
    }

    /**
     * CPU time of the process in microseconds, user and system time together like the cpu field of xhprof.
     */
    private static function cpuTime(): int
    {
        $usage = getrusage() ?: [];
        $microseconds = 0;
        foreach (['ru_utime.tv_sec' => 1000000, 'ru_stime.tv_sec' => 1000000, 'ru_utime.tv_usec' => 1, 'ru_stime.tv_usec' => 1] as $field => $multiplier) {
            $value = $usage[$field] ?? 0;
            if (\is_int($value)) {
                $microseconds += $value * $multiplier;
            }
        }

        return $microseconds;
    }
}
