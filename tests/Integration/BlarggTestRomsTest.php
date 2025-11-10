<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Blargg Test ROM Suite
 *
 * Runs Blargg's comprehensive CPU instruction tests.
 * These tests verify CPU instruction correctness and timing.
 */
final class BlarggTestRomsTest extends TestCase
{
    private const ROM_BASE_PATH = __DIR__ . '/../../third_party/roms';
    private const TIMEOUT = 60; // 60 seconds per test (increased for M-cycle accurate execution)

    private TestRomRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new TestRomRunner(self::TIMEOUT);
    }

    #[Test]
    #[DataProvider('cpuInstrsTestRomsProvider')]
    public function it_runs_cpu_instrs_test_rom(string $romName, string $romPath): void
    {
        $result = $this->runner->run($romPath);

        $message = sprintf(
            "%s\n%s\nFrames: %d, Duration: %.2fs\nOutput:\n%s",
            $romName,
            $result->getStatusString(),
            $result->frames,
            $result->duration,
            $result->output
        );

        $this->assertTrue(
            $result->passed(),
            $message
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function cpuInstrsTestRomsProvider(): array
    {
        $basePath = self::ROM_BASE_PATH . '/cpu_instrs/individual';

        return [
            '01-special' => [
                '01-special.gb',
                "{$basePath}/01-special.gb",
            ],
            '02-interrupts' => [
                '02-interrupts.gb',
                "{$basePath}/02-interrupts.gb",
            ],
            '03-op sp,hl' => [
                '03-op sp,hl.gb',
                "{$basePath}/03-op sp,hl.gb",
            ],
            '04-op r,imm' => [
                '04-op r,imm.gb',
                "{$basePath}/04-op r,imm.gb",
            ],
            '05-op rp' => [
                '05-op rp.gb',
                "{$basePath}/05-op rp.gb",
            ],
            '06-ld r,r' => [
                '06-ld r,r.gb',
                "{$basePath}/06-ld r,r.gb",
            ],
            '07-jr,jp,call,ret,rst' => [
                '07-jr,jp,call,ret,rst.gb',
                "{$basePath}/07-jr,jp,call,ret,rst.gb",
            ],
            '08-misc instrs' => [
                '08-misc instrs.gb',
                "{$basePath}/08-misc instrs.gb",
            ],
            '09-op r,r' => [
                '09-op r,r.gb',
                "{$basePath}/09-op r,r.gb",
            ],
            '10-bit ops' => [
                '10-bit ops.gb',
                "{$basePath}/10-bit ops.gb",
            ],
            '11-op a,(hl)' => [
                '11-op a,(hl).gb',
                "{$basePath}/11-op a,(hl).gb",
            ],
        ];
    }

    #[Test]
    public function it_runs_instr_timing_test_rom(): void
    {
        $romPath = self::ROM_BASE_PATH . '/instr_timing/instr_timing.gb';

        if (!file_exists($romPath)) {
            $this->markTestSkipped("ROM not found: {$romPath}");
        }

        $result = $this->runner->run($romPath);

        $message = sprintf(
            "instr_timing.gb\n%s\nFrames: %d, Duration: %.2fs\nOutput:\n%s",
            $result->getStatusString(),
            $result->frames,
            $result->duration,
            $result->output
        );

        $this->assertTrue(
            $result->passed(),
            $message
        );
    }
}
