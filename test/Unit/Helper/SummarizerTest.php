<?php

namespace DonlSync\Test\Unit\Helper;

use DonlSync\Exception\DonlSyncRuntimeException;
use DonlSync\Helper\Summarizer;
use PHPUnit\Framework\TestCase;

class SummarizerTest extends TestCase
{
    public function testKeysDefaultToZero(): void
    {
        $summarizer = new Summarizer();

        foreach (Summarizer::SUMMARY_KEYS as $key) {
            $this->assertEquals(0, $summarizer->get($key));
        }
    }

    public function testRequestingNonExistentKeyThrowsException(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage('unknown summary key foo');

        $summarizer = new Summarizer();
        $summarizer->get('foo');
    }

    public function testIncrementKeyThrowsExceptionOnNonExistentKey(): void
    {
        $this->expectException(DonlSyncRuntimeException::class);
        $this->expectExceptionMessage('unknown summary key foo');

        $summarizer = new Summarizer();
        $summarizer->incrementKey('foo');
    }

    public function testIncrementKeyIncreasesValueOfKeyByOne(): void
    {
        $summarizer = new Summarizer();

        $this->assertEquals(0, $summarizer->get(Summarizer::SUMMARY_KEYS[0]));
        $this->assertEquals(0, $summarizer->get(Summarizer::SUMMARY_KEYS[1]));

        $summarizer->incrementKey(Summarizer::SUMMARY_KEYS[0]);

        $this->assertEquals(1, $summarizer->get(Summarizer::SUMMARY_KEYS[0]));
        $this->assertEquals(0, $summarizer->get(Summarizer::SUMMARY_KEYS[1]));
    }
}
