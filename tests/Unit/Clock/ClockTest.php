<?php

declare(strict_types=1);

namespace Tests\Unit\Clock;

use Gb\Clock\Clock;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    #[Test]
    public function it_initializes_with_zero_cycles(): void
    {
        $clock = new Clock();
        $this->assertSame(0, $clock->getCycles());
    }

    #[Test]
    public function it_increments_cycles_on_tick(): void
    {
        $clock = new Clock();
        $clock->tick(4);
        $this->assertSame(4, $clock->getCycles());
    }

    #[Test]
    public function it_accumulates_cycles_on_tick(): void
    {
        $clock = new Clock();
        $clock->tick(4);
        $clock->tick(8);
        $clock->tick(12);
        $this->assertSame(24, $clock->getCycles());
    }

    #[Test]
    public function it_sets_cycles_to_zero_on_reset(): void
    {
        $clock = new Clock();
        $clock->tick(100);
        $clock->reset();
        $this->assertSame(0, $clock->getCycles());
    }

    #[Test]
    public function it_returns_correct_elapsed_delta(): void
    {
        $clock = new Clock();
        $checkpoint = 0;

        $clock->tick(4);
        $elapsed = $clock->getElapsed($checkpoint);

        $this->assertSame(4, $elapsed);
        $this->assertSame(4, $checkpoint);
    }

    #[Test]
    public function it_updates_checkpoint_on_get_elapsed(): void
    {
        $clock = new Clock();
        $checkpoint = 0;

        $clock->tick(4);
        $clock->getElapsed($checkpoint);

        $clock->tick(8);
        $elapsed = $clock->getElapsed($checkpoint);

        $this->assertSame(8, $elapsed);
        $this->assertSame(12, $checkpoint);
    }

    #[Test]
    public function it_handles_multiple_ticks_with_various_cycle_counts(): void
    {
        $clock = new Clock();

        $clock->tick(1);
        $this->assertSame(1, $clock->getCycles());

        $clock->tick(2);
        $this->assertSame(3, $clock->getCycles());

        $clock->tick(3);
        $this->assertSame(6, $clock->getCycles());

        $clock->tick(4);
        $this->assertSame(10, $clock->getCycles());
    }
}
