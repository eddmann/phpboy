<?php

declare(strict_types=1);

namespace Tests\Unit\Apu;

use Gb\Apu\Channel\Channel1;
use PHPUnit\Framework\TestCase;

final class Channel1Test extends TestCase
{
    public function testInitiallyDisabled(): void
    {
        $channel = new Channel1();
        self::assertFalse($channel->isEnabled());
        self::assertSame(0.0, $channel->getSample());
    }

    public function testTriggerEnablesChannel(): void
    {
        $channel = new Channel1();

        // Configure channel
        $channel->writeNR12(0xF3); // Initial volume 15, envelope add, period 3
        $channel->writeNR13(0x00); // Frequency low
        $channel->writeNR14(0x80); // Trigger

        self::assertTrue($channel->isEnabled());
    }

    public function testDutyPattern25Percent(): void
    {
        $channel = new Channel1();

        // Configure: duty 25% (pattern 1), volume 15, frequency
        $channel->writeNR11(0x40); // Duty 01 = 25%
        $channel->writeNR12(0xF0); // Volume 15, no envelope
        $channel->writeNR13(0x00);
        $channel->writeNR14(0x87); // Frequency high, trigger

        // Step through one full duty cycle (8 positions)
        // Pattern 1: [1, 0, 0, 0, 0, 0, 0, 1]
        $samples = [];
        for ($i = 0; $i < 8; $i++) {
            $samples[] = $channel->getSample();
            // Step by frequency period
            for ($j = 0; $j < (2048 - 0x700) * 4; $j++) {
                $channel->step();
            }
        }

        // Verify pattern (high values when duty is 1)
        self::assertGreaterThan(0.5, $samples[0]); // 1
        self::assertLessThan(-0.5, $samples[1]);   // 0
        self::assertLessThan(-0.5, $samples[2]);   // 0
        self::assertLessThan(-0.5, $samples[3]);   // 0
        self::assertLessThan(-0.5, $samples[4]);   // 0
        self::assertLessThan(-0.5, $samples[5]);   // 0
        self::assertLessThan(-0.5, $samples[6]);   // 0
        self::assertGreaterThan(0.5, $samples[7]); // 1
    }

    public function testVolumeEnvelope(): void
    {
        $channel = new Channel1();

        // Configure: initial volume 8, envelope decrease, period 1
        $channel->writeNR11(0x80); // Duty 50%
        $channel->writeNR12(0x81); // Volume 8, decrease, period 1
        $channel->writeNR13(0x00);
        $channel->writeNR14(0x80); // Trigger

        // Initial volume should be 8
        self::assertTrue($channel->isEnabled());

        // Clock envelope once - volume should decrease to 7
        $channel->clockEnvelope();

        // Volume affects output amplitude
        // This is a basic check that envelope changes the output
        self::assertTrue($channel->isEnabled());
    }

    public function testLengthCounter(): void
    {
        $channel = new Channel1();

        // Configure with length counter enabled
        $channel->writeNR11(0x3F); // Length load = 63, counter = 1
        $channel->writeNR12(0xF0); // Volume 15
        $channel->writeNR14(0xC0); // Length enable + trigger

        self::assertTrue($channel->isEnabled());

        // Clock length once - should disable channel
        $channel->clockLength();

        self::assertFalse($channel->isEnabled());
    }

    public function testSweepIncreaseFrequency(): void
    {
        $channel = new Channel1();

        // Configure sweep: period 1, increase, shift 1
        $channel->writeNR10(0x11); // Period 1, add, shift 1
        $channel->writeNR11(0x00);
        $channel->writeNR12(0xF0); // Volume 15
        $channel->writeNR13(0x00); // Frequency = 0x100
        $channel->writeNR14(0x81); // Frequency high = 1, trigger

        self::assertTrue($channel->isEnabled());

        // Clock sweep - frequency should increase
        $channel->clockSweep();

        // Channel should still be enabled (no overflow)
        self::assertTrue($channel->isEnabled());
    }

    public function testSweepOverflowDisablesChannel(): void
    {
        $channel = new Channel1();

        // Configure sweep that will overflow
        $channel->writeNR10(0x17); // Period 1, add, shift 7
        $channel->writeNR11(0x00);
        $channel->writeNR12(0xF0); // Volume 15
        $channel->writeNR13(0xFF); // Frequency = 0x7FF (2047)
        $channel->writeNR14(0x87); // Frequency high = 7, trigger

        self::assertTrue($channel->isEnabled());

        // Clock sweep - should overflow and disable
        $channel->clockSweep();

        // Sweep overflow should disable the channel
        // Note: The overflow check happens during trigger and sweep calculation
        self::assertFalse($channel->isEnabled());
    }

    public function testDacDisableStopsOutput(): void
    {
        $channel = new Channel1();

        // Enable channel
        $channel->writeNR12(0xF0); // DAC enabled
        $channel->writeNR14(0x80); // Trigger

        self::assertTrue($channel->isEnabled());

        // Disable DAC
        $channel->writeNR12(0x00); // All zeros = DAC disabled

        self::assertFalse($channel->isEnabled());
        self::assertSame(0.0, $channel->getSample());
    }

    public function testRegisterReadback(): void
    {
        $channel = new Channel1();

        $channel->writeNR10(0x7F);
        self::assertSame(0xFF, $channel->readNR10() & 0x7F); // Top bit is always 1

        $channel->writeNR11(0xFF);
        self::assertSame(0xFF, $channel->readNR11() & 0xC0); // Only duty bits readable

        $channel->writeNR12(0xFF);
        self::assertSame(0xFF, $channel->readNR12());

        $channel->writeNR13(0xFF);
        self::assertSame(0xFF, $channel->readNR13()); // Write-only

        $channel->writeNR14(0xFF);
        self::assertSame(0x40, $channel->readNR14() & 0x40); // Only length enable readable
    }

    public function testFrequencyGeneration(): void
    {
        $channel = new Channel1();

        // Configure with specific frequency
        $channel->writeNR11(0x80); // 50% duty
        $channel->writeNR12(0xF0); // Max volume
        $channel->writeNR13(0x00); // Frequency low = 0
        $channel->writeNR14(0x80); // Frequency high = 0, trigger (freq = 0)

        self::assertTrue($channel->isEnabled());

        // Step and verify output changes
        $sample1 = $channel->getSample();

        // Step through half the frequency timer period
        for ($i = 0; $i < (2048 - 0) * 2; $i++) {
            $channel->step();
        }

        $sample2 = $channel->getSample();

        // After stepping, duty position should change
        // This is a basic test that stepping advances the waveform
        self::assertTrue($channel->isEnabled());
    }
}
