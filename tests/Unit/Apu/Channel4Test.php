<?php

declare(strict_types=1);

namespace Tests\Unit\Apu;

use Gb\Apu\Channel\Channel4;
use PHPUnit\Framework\TestCase;

final class Channel4Test extends TestCase
{
    public function testInitiallyDisabled(): void
    {
        $channel = new Channel4();
        self::assertFalse($channel->isEnabled());
        self::assertSame(0.0, $channel->getSample());
    }

    public function testTriggerEnablesChannel(): void
    {
        $channel = new Channel4();

        $channel->writeNR42(0xF0); // Volume 15
        $channel->writeNR44(0x80); // Trigger

        self::assertTrue($channel->isEnabled());
    }

    public function testNoiseGeneration(): void
    {
        $channel = new Channel4();

        $channel->writeNR42(0xF0); // Volume 15
        $channel->writeNR43(0x00); // Clock shift 0, divisor 0
        $channel->writeNR44(0x80); // Trigger

        self::assertTrue($channel->isEnabled());

        // Collect samples - should have variation (noise)
        $samples = [];
        for ($i = 0; $i < 100; $i++) {
            $samples[] = $channel->getSample();
            $channel->step();
        }

        // At least some samples should differ
        $uniqueSamples = array_unique($samples);
        self::assertGreaterThan(1, count($uniqueSamples));
    }

    public function testVolumeEnvelope(): void
    {
        $channel = new Channel4();

        $channel->writeNR42(0xF1); // Volume 15, decrease, period 1
        $channel->writeNR44(0x80);

        self::assertTrue($channel->isEnabled());

        $channel->clockEnvelope();
        self::assertTrue($channel->isEnabled());
    }

    public function testLengthCounter(): void
    {
        $channel = new Channel4();

        $channel->writeNR41(0x3F); // Length = 1
        $channel->writeNR42(0xF0);
        $channel->writeNR44(0xC0); // Length enable + trigger

        self::assertTrue($channel->isEnabled());

        $channel->clockLength();
        self::assertFalse($channel->isEnabled());
    }

    public function testDacDisable(): void
    {
        $channel = new Channel4();

        $channel->writeNR42(0xF0);
        $channel->writeNR44(0x80);
        self::assertTrue($channel->isEnabled());

        $channel->writeNR42(0x00); // Disable DAC
        self::assertFalse($channel->isEnabled());
        self::assertSame(0.0, $channel->getSample());
    }

    public function testWidthMode7Bit(): void
    {
        $channel = new Channel4();

        $channel->writeNR42(0xF0);
        $channel->writeNR43(0x08); // Width mode 1 (7-bit)
        $channel->writeNR44(0x80);

        self::assertTrue($channel->isEnabled());

        // Just verify it generates samples in 7-bit mode
        for ($i = 0; $i < 10; $i++) {
            $channel->step();
        }

        self::assertTrue($channel->isEnabled());
    }

    public function testDivisorCodes(): void
    {
        $channel = new Channel4();

        // Test different divisor codes
        for ($divisor = 0; $divisor < 8; $divisor++) {
            $channel->writeNR42(0xF0);
            $channel->writeNR43($divisor); // Different divisors
            $channel->writeNR44(0x80);

            self::assertTrue($channel->isEnabled(), "Divisor $divisor should enable channel");
        }
    }

    public function testClockShift(): void
    {
        $channel = new Channel4();

        $channel->writeNR42(0xF0);
        $channel->writeNR43(0x70); // Clock shift 7, divisor 0
        $channel->writeNR44(0x80);

        self::assertTrue($channel->isEnabled());

        // Higher clock shift means slower frequency
        // Just verify it doesn't break
        for ($i = 0; $i < 100; $i++) {
            $channel->step();
        }

        self::assertTrue($channel->isEnabled());
    }
}
