<?php

declare(strict_types=1);

namespace Tests\Unit\Clock;

use Gb\Clock\Clock;
use PHPUnit\Framework\TestCase;

final class ClockTest extends TestCase
{
    public function testInitializesWithZeroCycles(): void
    {
        $clock = new Clock();
        $this->assertSame(0, $clock->getCycles());
    }

    public function testTickIncrementsCycles(): void
    {
        $clock = new Clock();
        $clock->tick(4);
        $this->assertSame(4, $clock->getCycles());
    }

    public function testTickAccumulatesCycles(): void
    {
        $clock = new Clock();
        $clock->tick(4);
        $clock->tick(8);
        $clock->tick(12);
        $this->assertSame(24, $clock->getCycles());
    }

    public function testResetSetsCyclesToZero(): void
    {
        $clock = new Clock();
        $clock->tick(100);
        $clock->reset();
        $this->assertSame(0, $clock->getCycles());
    }

    public function testGetElapsedReturnsCorrectDelta(): void
    {
        $clock = new Clock();
        $checkpoint = 0;

        $clock->tick(4);
        $elapsed = $clock->getElapsed($checkpoint);

        $this->assertSame(4, $elapsed);
        $this->assertSame(4, $checkpoint);
    }

    public function testGetElapsedUpdatesCheckpoint(): void
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

    public function testMultipleTicksWithVariousCycleCounts(): void
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
