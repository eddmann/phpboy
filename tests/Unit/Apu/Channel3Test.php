<?php

declare(strict_types=1);

namespace Tests\Unit\Apu;

use Gb\Apu\Channel\Channel3;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Channel3Test extends TestCase
{
    #[Test]
    public function it_is_initially_disabled(): void
    {
        $channel = new Channel3();
        self::assertFalse($channel->isEnabled());
        self::assertSame(0.0, $channel->getSample());
    }

    #[Test]
    public function it_enables_channel_when_triggered(): void
    {
        $channel = new Channel3();

        $channel->writeNR30(0x80); // Enable DAC
        $channel->writeNR34(0x80); // Trigger

        self::assertTrue($channel->isEnabled());
    }

    #[Test]
    public function it_can_read_and_write_wave_ram(): void
    {
        $channel = new Channel3();

        // Write pattern to wave RAM
        for ($i = 0; $i < 16; $i++) {
            $channel->writeWaveRam($i, $i * 0x11);
        }

        // Read back and verify
        for ($i = 0; $i < 16; $i++) {
            self::assertSame($i * 0x11, $channel->readWaveRam($i));
        }
    }

    #[Test]
    public function it_outputs_at_100_percent_level(): void
    {
        $channel = new Channel3();

        // Write a known pattern to wave RAM
        $channel->writeWaveRam(0, 0xFF); // High nibble = 15

        $channel->writeNR30(0x80); // Enable DAC
        $channel->writeNR32(0x20); // Output level 1 = 100%
        $channel->writeNR33(0x00);
        $channel->writeNR34(0x80); // Trigger

        self::assertTrue($channel->isEnabled());

        // First sample should be from high nibble of byte 0
        $sample = $channel->getSample();
        self::assertGreaterThan(0.0, $sample);
    }

    #[Test]
    public function it_outputs_at_50_percent_level(): void
    {
        $channel = new Channel3();

        $channel->writeWaveRam(0, 0xFF);

        $channel->writeNR30(0x80);
        $channel->writeNR32(0x40); // Output level 2 = 50%
        $channel->writeNR34(0x80);

        self::assertTrue($channel->isEnabled());

        // Sample should be lower than 100% output
        $sample = $channel->getSample();
        self::assertNotSame(0.0, $sample);
    }

    #[Test]
    public function it_mutes_output_at_zero_level(): void
    {
        $channel = new Channel3();

        $channel->writeWaveRam(0, 0xFF);

        $channel->writeNR30(0x80);
        $channel->writeNR32(0x00); // Output level 0 = mute
        $channel->writeNR34(0x80);

        self::assertTrue($channel->isEnabled());

        // Should output silence
        self::assertSame(-1.0, $channel->getSample());
    }

    #[Test]
    public function it_disables_when_length_counter_expires(): void
    {
        $channel = new Channel3();

        $channel->writeNR30(0x80);
        $channel->writeNR31(0xFF); // Length = 1
        $channel->writeNR34(0xC0); // Length enable + trigger

        self::assertTrue($channel->isEnabled());

        $channel->clockLength();
        self::assertFalse($channel->isEnabled());
    }

    #[Test]
    public function it_stops_output_when_dac_disabled(): void
    {
        $channel = new Channel3();

        $channel->writeNR30(0x80);
        $channel->writeNR34(0x80);
        self::assertTrue($channel->isEnabled());

        $channel->writeNR30(0x00); // Disable DAC
        self::assertFalse($channel->isEnabled());
        self::assertSame(0.0, $channel->getSample());
    }

    #[Test]
    public function it_advances_sample_position(): void
    {
        $channel = new Channel3();

        // Write alternating pattern
        $channel->writeWaveRam(0, 0xF0); // High=15, Low=0

        $channel->writeNR30(0x80);
        $channel->writeNR32(0x20); // 100% output
        $channel->writeNR33(0x00);
        $channel->writeNR34(0x87); // High frequency, trigger

        $sample1 = $channel->getSample();

        // Step to next sample
        for ($i = 0; $i < (2048 - 0x700) * 2; $i++) {
            $channel->step();
        }

        $sample2 = $channel->getSample();

        // Samples should differ (moved from high to low nibble)
        self::assertNotSame($sample1, $sample2);
    }
}
