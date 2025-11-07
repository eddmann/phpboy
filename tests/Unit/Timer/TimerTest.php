<?php

declare(strict_types=1);

namespace Tests\Unit\Timer;

use Gb\Interrupts\InterruptController;
use Gb\Interrupts\InterruptType;
use Gb\Timer\Timer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TimerTest extends TestCase
{
    private InterruptController $interruptController;
    private Timer $timer;

    protected function setUp(): void
    {
        $this->interruptController = new InterruptController();
        $this->timer = new Timer($this->interruptController);
    }

    #[Test]
    public function it_initializes_with_correct_defaults(): void
    {
        $this->assertSame(0x00, $this->timer->readByte(0xFF04)); // DIV
        $this->assertSame(0x00, $this->timer->readByte(0xFF05)); // TIMA
        $this->assertSame(0x00, $this->timer->readByte(0xFF06)); // TMA
        $this->assertSame(0xF8, $this->timer->readByte(0xFF07)); // TAC (upper 5 bits=1)
    }

    #[Test]
    public function it_reads_and_writes_tima(): void
    {
        $this->timer->writeByte(0xFF05, 0xAB);
        $this->assertSame(0xAB, $this->timer->readByte(0xFF05));
    }

    #[Test]
    public function it_reads_and_writes_tma(): void
    {
        $this->timer->writeByte(0xFF06, 0xCD);
        $this->assertSame(0xCD, $this->timer->readByte(0xFF06));
    }

    #[Test]
    public function it_reads_and_writes_tac(): void
    {
        $this->timer->writeByte(0xFF07, 0x05); // Enable + 4096 Hz
        // Upper 5 bits should read as 1
        $this->assertSame(0xFD, $this->timer->readByte(0xFF07));
    }

    #[Test]
    public function it_masks_tac_to_3_bits(): void
    {
        $this->timer->writeByte(0xFF07, 0xFF);
        $this->assertSame(0xFF, $this->timer->readByte(0xFF07)); // 0x07 | 0xF8 = 0xFF
    }

    #[Test]
    public function it_resets_div_on_write(): void
    {
        // Advance DIV
        for ($i = 0; $i < 10; $i++) {
            $this->timer->tick(256); // Should increment DIV by 10
        }
        $this->assertSame(0x0A, $this->timer->readByte(0xFF04));

        // Write to DIV (any value resets it)
        $this->timer->writeByte(0xFF04, 0xFF);
        $this->assertSame(0x00, $this->timer->readByte(0xFF04));
    }

    #[Test]
    public function it_increments_div_every_256_cycles(): void
    {
        // DIV increments every 256 CPU cycles
        $this->timer->tick(255);
        $this->assertSame(0x00, $this->timer->readByte(0xFF04));

        $this->timer->tick(1);
        $this->assertSame(0x01, $this->timer->readByte(0xFF04));

        $this->timer->tick(256);
        $this->assertSame(0x02, $this->timer->readByte(0xFF04));
    }

    #[Test]
    public function it_wraps_div_at_255(): void
    {
        // Advance DIV to 255
        $this->timer->tick(256 * 255);
        $this->assertSame(0xFF, $this->timer->readByte(0xFF04));

        // One more increment should wrap to 0
        $this->timer->tick(256);
        $this->assertSame(0x00, $this->timer->readByte(0xFF04));
    }

    #[Test]
    public function it_does_not_increment_tima_when_disabled(): void
    {
        // TAC enable bit (bit 2) = 0
        $this->timer->writeByte(0xFF07, 0x00);
        $this->timer->writeByte(0xFF05, 0x00);

        // Tick many cycles
        $this->timer->tick(10000);

        // TIMA should not have incremented
        $this->assertSame(0x00, $this->timer->readByte(0xFF05));
    }

    #[Test]
    public function it_increments_tima_at_4096_hz(): void
    {
        // Enable timer at 4096 Hz (TAC=0b101: enable=1, freq=00)
        $this->timer->writeByte(0xFF07, 0x04);
        $this->timer->writeByte(0xFF05, 0x00);

        // 4096 Hz = 1024 cycles per increment
        $this->timer->tick(1023);
        $this->assertSame(0x00, $this->timer->readByte(0xFF05));

        $this->timer->tick(1);
        $this->assertSame(0x01, $this->timer->readByte(0xFF05));

        $this->timer->tick(1024);
        $this->assertSame(0x02, $this->timer->readByte(0xFF05));
    }

    #[Test]
    public function it_increments_tima_at_262144_hz(): void
    {
        // Enable timer at 262144 Hz (TAC=0b101: enable=1, freq=01)
        $this->timer->writeByte(0xFF07, 0x05);
        $this->timer->writeByte(0xFF05, 0x00);

        // 262144 Hz = 16 cycles per increment
        $this->timer->tick(15);
        $this->assertSame(0x00, $this->timer->readByte(0xFF05));

        $this->timer->tick(1);
        $this->assertSame(0x01, $this->timer->readByte(0xFF05));
    }

    #[Test]
    public function it_increments_tima_at_65536_hz(): void
    {
        // Enable timer at 65536 Hz (TAC=0b110: enable=1, freq=10)
        $this->timer->writeByte(0xFF07, 0x06);
        $this->timer->writeByte(0xFF05, 0x00);

        // 65536 Hz = 64 cycles per increment
        $this->timer->tick(63);
        $this->assertSame(0x00, $this->timer->readByte(0xFF05));

        $this->timer->tick(1);
        $this->assertSame(0x01, $this->timer->readByte(0xFF05));
    }

    #[Test]
    public function it_increments_tima_at_16384_hz(): void
    {
        // Enable timer at 16384 Hz (TAC=0b111: enable=1, freq=11)
        $this->timer->writeByte(0xFF07, 0x07);
        $this->timer->writeByte(0xFF05, 0x00);

        // 16384 Hz = 256 cycles per increment
        $this->timer->tick(255);
        $this->assertSame(0x00, $this->timer->readByte(0xFF05));

        $this->timer->tick(1);
        $this->assertSame(0x01, $this->timer->readByte(0xFF05));
    }

    #[Test]
    public function it_reloads_tima_from_tma_on_overflow(): void
    {
        // Set TMA to 0xAB
        $this->timer->writeByte(0xFF06, 0xAB);
        // Set TIMA to 0xFF (one away from overflow)
        $this->timer->writeByte(0xFF05, 0xFF);
        // Enable timer at 4096 Hz
        $this->timer->writeByte(0xFF07, 0x04);

        // Tick enough to increment TIMA once
        $this->timer->tick(1024);

        // TIMA should have overflowed and reloaded from TMA
        $this->assertSame(0xAB, $this->timer->readByte(0xFF05));
    }

    #[Test]
    public function it_requests_timer_interrupt_on_overflow(): void
    {
        // Enable interrupts
        $this->interruptController->writeByte(0xFFFF, 0x04); // Enable Timer interrupt

        // Set TIMA to 0xFF
        $this->timer->writeByte(0xFF05, 0xFF);
        // Enable timer at 4096 Hz
        $this->timer->writeByte(0xFF07, 0x04);

        // No interrupt yet
        $this->assertNull($this->interruptController->getPendingInterrupt());

        // Tick enough to overflow TIMA
        $this->timer->tick(1024);

        // Timer interrupt should be requested
        $this->assertSame(InterruptType::Timer, $this->interruptController->getPendingInterrupt());
    }

    #[Test]
    public function it_handles_multiple_tima_increments_in_one_tick(): void
    {
        // Enable timer at 262144 Hz (16 cycles per increment)
        $this->timer->writeByte(0xFF07, 0x05);
        $this->timer->writeByte(0xFF05, 0x00);

        // Tick 160 cycles = 10 increments
        $this->timer->tick(160);

        $this->assertSame(0x0A, $this->timer->readByte(0xFF05));
    }

    #[Test]
    public function it_updates_both_div_and_tima_simultaneously(): void
    {
        // Enable timer at 4096 Hz
        $this->timer->writeByte(0xFF07, 0x04);

        // Tick 1024 cycles
        // DIV should increment by 4 (1024 / 256 = 4)
        // TIMA should increment by 1 (1024 / 1024 = 1)
        $this->timer->tick(1024);

        $this->assertSame(0x04, $this->timer->readByte(0xFF04)); // DIV
        $this->assertSame(0x01, $this->timer->readByte(0xFF05)); // TIMA
    }
}
