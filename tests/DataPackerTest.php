<?php

declare(strict_types=1);

namespace Badoo\LiveProfiler\Tests;

use Badoo\LiveProfiler\DataPacker;
use PHPUnit\Framework\TestCase;

final class DataPackerTest extends TestCase
{
    public function testPackProducesJson(): void
    {
        self::assertSame('{"main()":{"wt":1,"ct":2}}', (new DataPacker())->pack(['main()' => ['wt' => 1, 'ct' => 2]]));
    }

    public function testUnpackRestoresThePackedData(): void
    {
        $packer = new DataPacker();
        $data = ['main()' => ['wt' => 1, 'ct' => 2], 'main()==>foo' => ['wt' => 1, 'ct' => 1]];

        self::assertSame($data, $packer->unpack($packer->pack($data)));
    }

    public function testUnpackReturnsAnEmptyArrayForNonArrayJson(): void
    {
        self::assertSame([], (new DataPacker())->unpack('"string"'));
        self::assertSame([], (new DataPacker())->unpack('null'));
    }

    public function testUnpackReturnsAnEmptyArrayForInvalidJson(): void
    {
        self::assertSame([], (new DataPacker())->unpack('{invalid'));
    }

    public function testPackRejectsDataThatCanNotBeEncoded(): void
    {
        $this->expectException(\JsonException::class);

        (new DataPacker())->pack(["\xB1\x31" => []]);
    }
}
