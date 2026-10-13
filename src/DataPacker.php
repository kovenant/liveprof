<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler;

/**
 * Stores profiles as JSON.
 */
class DataPacker implements DataPackerInterface
{
    /**
     * @throws \JsonException when the profile can't be encoded, for instance because of invalid UTF-8 in a method name
     */
    public function pack(array $data): string
    {
        return json_encode($data, \JSON_THROW_ON_ERROR);
    }

    /**
     * Returns an empty array for invalid JSON and for JSON that is not an object or an array.
     *
     * @return array<mixed>
     */
    public function unpack(string $data): array
    {
        $decoded = json_decode($data, true);

        return \is_array($decoded) ? $decoded : [];
    }
}
