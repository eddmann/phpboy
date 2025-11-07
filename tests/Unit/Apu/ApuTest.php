<?php

declare(strict_types=1);

namespace Tests\Unit\Apu;

use Gb\Apu\Apu;
use Gb\Apu\Sink\BufferSink;
use Gb\Apu\Sink\NullSink;
use PHPUnit\Framework\TestCase;

final class ApuTest extends TestCase
{
    public function testInitiallyDisabled(): void
    {
        $apu = new Apu(new NullSink());

        // NR52 bit 7 should be 0 (disabled)
        self::assertSame(0x70, $apu->readByte(0xFF26));
    }

    public function testEnableApu(): void
    {
        $apu = new Apu(new NullSink());

        $apu->writeByte(0xFF26, 0x80); // Enable APU

        self::assertSame(0xF0, $apu->readByte(0xFF26) & 0xF0);
    }

    public function testDisableApuClearsChannels(): void
    {
        $apu = new Apu(new NullSink());

        // Enable APU
        $apu->writeByte(0xFF26, 0x80);

        // Enable channel 1
        $apu->writeByte(0xFF12, 0xF0); // Volume
        $apu->writeByte(0xFF14, 0x80); // Trigger

        // Check channel 1 is enabled (bit 0 of NR52)
        self::assertSame(0x01, $apu->readByte(0xFF26) & 0x01);

        // Disable APU
        $apu->writeByte(0xFF26, 0x00);

        // Channel should be disabled
        self::assertSame(0x00, $apu->readByte(0xFF26) & 0x0F);
    }

    public function testCannotWriteRegistersWhenDisabled(): void
    {
        $apu = new Apu(new NullSink());

        // APU is disabled by default
        $apu->writeByte(0xFF12, 0xF0); // Try to write NR12

        // Register should not be affected (will read back as default)
        self::assertSame(0x00, $apu->readByte(0xFF12));
    }

    public function testWaveRamAccessWhenDisabled(): void
    {
        $apu = new Apu(new NullSink());

        // APU disabled, but wave RAM should still be accessible
        $apu->writeByte(0xFF30, 0xAB);

        self::assertSame(0xAB, $apu->readByte(0xFF30));
    }

    public function testChannel1Registers(): void
    {
        $apu = new Apu(new NullSink());

        $apu->writeByte(0xFF26, 0x80); // Enable APU

        $apu->writeByte(0xFF10, 0x7F);
        $apu->writeByte(0xFF11, 0xFF);
        $apu->writeByte(0xFF12, 0xF3);
        $apu->writeByte(0xFF13, 0xAB);
        $apu->writeByte(0xFF14, 0x47);

        // Verify readback (some bits are write-only)
        self::assertSame(0xFF, $apu->readByte(0xFF10));
        self::assertSame(0xFF, $apu->readByte(0xFF11) & 0xC0);
        self::assertSame(0xF3, $apu->readByte(0xFF12));
        self::assertSame(0xFF, $apu->readByte(0xFF13)); // Write-only
        self::assertSame(0x40, $apu->readByte(0xFF14) & 0x40);
    }

    public function testChannel2Registers(): void
    {
        $apu = new Apu(new NullSink());

        $apu->writeByte(0xFF26, 0x80);

        $apu->writeByte(0xFF16, 0xFF);
        $apu->writeByte(0xFF17, 0xF0);
        $apu->writeByte(0xFF18, 0xCD);
        $apu->writeByte(0xFF19, 0x40);

        self::assertSame(0xFF, $apu->readByte(0xFF16) & 0xC0);
        self::assertSame(0xF0, $apu->readByte(0xFF17));
        self::assertSame(0xFF, $apu->readByte(0xFF18));
        self::assertSame(0x40, $apu->readByte(0xFF19) & 0x40);
    }

    public function testChannel3Registers(): void
    {
        $apu = new Apu(new NullSink());

        $apu->writeByte(0xFF26, 0x80);

        $apu->writeByte(0xFF1A, 0x80);
        $apu->writeByte(0xFF1B, 0x12);
        $apu->writeByte(0xFF1C, 0x60);
        $apu->writeByte(0xFF1D, 0xEF);
        $apu->writeByte(0xFF1E, 0x47);

        self::assertSame(0x80, $apu->readByte(0xFF1A) & 0x80);
        self::assertSame(0xFF, $apu->readByte(0xFF1B));
        self::assertSame(0x60, $apu->readByte(0xFF1C) & 0x60);
        self::assertSame(0xFF, $apu->readByte(0xFF1D));
        self::assertSame(0x40, $apu->readByte(0xFF1E) & 0x40);
    }

    public function testChannel4Registers(): void
    {
        $apu = new Apu(new NullSink());

        $apu->writeByte(0xFF26, 0x80);

        $apu->writeByte(0xFF20, 0x3F);
        $apu->writeByte(0xFF21, 0xF7);
        $apu->writeByte(0xFF22, 0xAB);
        $apu->writeByte(0xFF23, 0x40);

        self::assertSame(0xFF, $apu->readByte(0xFF20));
        self::assertSame(0xF7, $apu->readByte(0xFF21));
        self::assertSame(0xAB, $apu->readByte(0xFF22));
        self::assertSame(0x40, $apu->readByte(0xFF23) & 0x40);
    }

    public function testMasterVolumeRegister(): void
    {
        $apu = new Apu(new NullSink());

        $apu->writeByte(0xFF26, 0x80);
        $apu->writeByte(0xFF24, 0xFF); // Max volume left and right

        self::assertSame(0xFF, $apu->readByte(0xFF24));
    }

    public function testPanningRegister(): void
    {
        $apu = new Apu(new NullSink());

        $apu->writeByte(0xFF26, 0x80);
        $apu->writeByte(0xFF25, 0xAB); // Custom panning

        self::assertSame(0xAB, $apu->readByte(0xFF25));
    }

    public function testWaveRamReadWrite(): void
    {
        $apu = new Apu(new NullSink());

        $apu->writeByte(0xFF26, 0x80);

        // Write pattern to wave RAM
        for ($i = 0; $i < 16; $i++) {
            $apu->writeByte(0xFF30 + $i, $i * 0x11);
        }

        // Read back
        for ($i = 0; $i < 16; $i++) {
            self::assertSame($i * 0x11, $apu->readByte(0xFF30 + $i));
        }
    }

    public function testFrameSequencerStepsChannels(): void
    {
        $apu = new Apu(new NullSink());

        $apu->writeByte(0xFF26, 0x80); // Enable APU

        // Enable channel 1 with length counter
        $apu->writeByte(0xFF11, 0x3F); // Length = 1
        $apu->writeByte(0xFF12, 0xF0); // Volume 15
        $apu->writeByte(0xFF14, 0xC0); // Length enable + trigger

        // Verify channel is enabled
        self::assertSame(0x01, $apu->readByte(0xFF26) & 0x01);

        // Step through frame sequencer (8192 cycles per step)
        // Step 0 clocks length counter
        $apu->step(8192);

        // Channel should be disabled after length counter expires
        self::assertSame(0x00, $apu->readByte(0xFF26) & 0x01);
    }

    public function testAudioSampleGeneration(): void
    {
        $sink = new BufferSink();
        $apu = new Apu($sink);

        $apu->writeByte(0xFF26, 0x80); // Enable APU
        $apu->writeByte(0xFF24, 0x77); // Master volume
        $apu->writeByte(0xFF25, 0xFF); // All channels to both speakers

        // Enable channel 1
        $apu->writeByte(0xFF11, 0x80); // 50% duty
        $apu->writeByte(0xFF12, 0xF0); // Volume 15
        $apu->writeByte(0xFF13, 0x00);
        $apu->writeByte(0xFF14, 0x87); // Trigger with frequency

        // Step to generate samples (~95 cycles per sample at 44100 Hz)
        $apu->step(1000);

        // Verify samples were generated
        self::assertGreaterThan(0, $sink->getSampleCount());
    }

    public function testStereoMixing(): void
    {
        $sink = new BufferSink();
        $apu = new Apu($sink);

        $apu->writeByte(0xFF26, 0x80);
        $apu->writeByte(0xFF24, 0x77);

        // Pan channel 1 to left only (bit 4 of NR51)
        $apu->writeByte(0xFF25, 0x10);

        // Enable channel 1
        $apu->writeByte(0xFF12, 0xF0);
        $apu->writeByte(0xFF14, 0x87);

        $apu->step(1000);

        // Samples should be generated
        self::assertGreaterThan(0, $sink->getSampleCount());
    }

    public function testNR52ChannelStatus(): void
    {
        $apu = new Apu(new NullSink());

        $apu->writeByte(0xFF26, 0x80); // Enable APU

        // Initially no channels enabled
        self::assertSame(0x00, $apu->readByte(0xFF26) & 0x0F);

        // Enable channel 1
        $apu->writeByte(0xFF12, 0xF0);
        $apu->writeByte(0xFF14, 0x80);
        self::assertSame(0x01, $apu->readByte(0xFF26) & 0x0F);

        // Enable channel 2
        $apu->writeByte(0xFF17, 0xF0);
        $apu->writeByte(0xFF19, 0x80);
        self::assertSame(0x03, $apu->readByte(0xFF26) & 0x0F);

        // Enable channel 3
        $apu->writeByte(0xFF1A, 0x80);
        $apu->writeByte(0xFF1E, 0x80);
        self::assertSame(0x07, $apu->readByte(0xFF26) & 0x0F);

        // Enable channel 4
        $apu->writeByte(0xFF21, 0xF0);
        $apu->writeByte(0xFF23, 0x80);
        self::assertSame(0x0F, $apu->readByte(0xFF26) & 0x0F);
    }
}
