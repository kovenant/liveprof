<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler;

/**
 * Converts a profile into the string stored in the database, and back.
 */
interface DataPackerInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function pack(array $data): string;

    /**
     * @return array<mixed>
     */
    public function unpack(string $data): array;
}
