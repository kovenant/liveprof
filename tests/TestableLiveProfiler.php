<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler\Tests;

use Badoo\LiveProfiler\LiveProfiler;

/**
 * Records the saved profiles instead of storing them and exposes the internal state.
 */
final class TestableLiveProfiler extends LiveProfiler
{
    /** @var list<array{app: string, label: string, datetime: string, data: array<string, mixed>}> */
    public array $saved = [];

    public bool $saveResult = true;

    protected function save(string $app, string $label, string $datetime, array $data): bool
    {
        $this->saved[] = ['app' => $app, 'label' => $label, 'datetime' => $datetime, 'data' => $data];

        return $this->saveResult;
    }

    public function withoutProfiler(): self
    {
        $this->startCallback = null;
        $this->endCallback = null;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->isEnabled;
    }

    public function getStartCallback(): ?\Closure
    {
        return $this->startCallback;
    }

    public function getEndCallback(): ?\Closure
    {
        return $this->endCallback;
    }

    public function property(string $name): mixed
    {
        return $this->{$name};
    }

    /**
     * @param array<array-key, string> $samplingData
     *
     * @return array<string, array{ct: int, wt: int}>
     */
    public function convertSamples(array $samplingData, float $samplingStartedAt): array
    {
        return $this->convertSampleDataToCommonFormat($samplingData, $samplingStartedAt);
    }
}
