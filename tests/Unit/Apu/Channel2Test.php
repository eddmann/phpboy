<?php

declare(strict_types=1);

namespace Tests\Unit\Apu;

use Gb\Apu\Channel\Channel2;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Channel2Test extends TestCase
{
    #[Test]
    public function it_is_initially_disabled(): void
    {
        $channel = new Channel2();
        self::assertFalse($channel->isEnabled());
        self::assertSame(0.0, $channel->getSample());
    }

    #[Test]
    public function it_enables_channel_when_triggered(): void
    {
        $channel = new Channel2();

        $channel->writeNR22(0xF0); // Volume 15
        $channel->writeNR24(0x80); // Trigger

        self::assertTrue($channel->isEnabled());
    }

    #[Test]
    public function it_produces_50_percent_duty_pattern(): void
    {
        $channel = new Channel2();

        // Configure: duty 50% (pattern 2), volume 15
        $channel->writeNR21(0x80); // Duty 10 = 50%
        $channel->writeNR22(0xF0); // Volume 15
        $channel->writeNR23(0x00);
        $channel->writeNR24(0x87); // Trigger

        // Step through one duty cycle
        $samples = [];
        for ($i = 0; $i < 8; $i++) {
            $samples[] = $channel->getSample();
            for ($j = 0; $j < (2048 - 0x700) * 4; $j++) {
                $channel->step();
            }
        }

        // Pattern 2: [1, 0, 0, 0, 0, 1, 1, 1] (50%)
        self::assertGreaterThan(0.5, $samples[0]);
        self::assertLessThan(-0.5, $samples[1]);
        self::assertLessThan(-0.5, $samples[2]);
    }

    #[Test]
    public function it_applies_volume_envelope(): void
    {
        $channel = new Channel2();

        // Configure with envelope increase
        $channel->writeNR21(0x00);
        $channel->writeNR22(0x08); // Volume 0, increase, period 0
        $channel->writeNR24(0x80); // Trigger

        self::assertTrue($channel->isEnabled());
    }

    #[Test]
    public function it_disables_when_length_counter_expires(): void
    {
        $channel = new Channel2();

        $channel->writeNR21(0x3F); // Length = 1
        $channel->writeNR22(0xF0);
        $channel->writeNR24(0xC0); // Length enable + trigger

        self::assertTrue($channel->isEnabled());

        $channel->clockLength();
        self::assertFalse($channel->isEnabled());
    }

    #[Test]
    public function it_stops_output_when_dac_disabled(): void
    {
        $channel = new Channel2();

        $channel->writeNR22(0xF0);
        $channel->writeNR24(0x80);
        self::assertTrue($channel->isEnabled());

        $channel->writeNR22(0x00); // Disable DAC
        self::assertFalse($channel->isEnabled());
        self::assertSame(0.0, $channel->getSample());
    }
}
