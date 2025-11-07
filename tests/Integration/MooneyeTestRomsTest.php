<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Mooneye Test ROM Suite
 *
 * Runs Mooneye's acceptance test ROMs.
 * These tests verify CPU, PPU, timer, and other component behavior.
 *
 * Mooneye tests use register-based pass/fail detection:
 * - Pass: B/C/D/E/H/L = 3/5/8/13/21/34 (Fibonacci sequence)
 * - Fail: B/C/D/E/H/L = 0x42 (all registers)
 */
final class MooneyeTestRomsTest extends TestCase
{
    private const ROM_BASE_PATH = __DIR__ . '/../../third_party/roms/mooneye-bins/acceptance';
    private const TIMEOUT = 10; // 10 seconds per test (Mooneye tests are typically fast)

    private TestRomRunner $runner;

    protected function setUp(): void
    {
        $this->runner = new TestRomRunner(self::TIMEOUT);
    }

    /**
     * @dataProvider acceptanceTestRomsProvider
     */
    public function testAcceptance(string $romName, string $romPath): void
    {
        if (!file_exists($romPath)) {
            $this->markTestSkipped("ROM not found: {$romPath}");
        }

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
    public static function acceptanceTestRomsProvider(): array
    {
        $basePath = self::ROM_BASE_PATH;

        return [
            // Basic instruction tests
            'add_sp_e_timing' => [
                'add_sp_e_timing.gb',
                "{$basePath}/add_sp_e_timing.gb",
            ],
            'call_cc_timing' => [
                'call_cc_timing.gb',
                "{$basePath}/call_cc_timing.gb",
            ],
            'call_timing' => [
                'call_timing.gb',
                "{$basePath}/call_timing.gb",
            ],
            'di_timing-GS' => [
                'di_timing-GS.gb',
                "{$basePath}/di_timing-GS.gb",
            ],
            'ei_sequence' => [
                'ei_sequence.gb',
                "{$basePath}/ei_sequence.gb",
            ],
            'ei_timing' => [
                'ei_timing.gb',
                "{$basePath}/ei_timing.gb",
            ],
            'halt_ime0_ei' => [
                'halt_ime0_ei.gb',
                "{$basePath}/halt_ime0_ei.gb",
            ],
            'halt_ime0_nointr_timing' => [
                'halt_ime0_nointr_timing.gb',
                "{$basePath}/halt_ime0_nointr_timing.gb",
            ],
            'halt_ime1_timing' => [
                'halt_ime1_timing.gb',
                "{$basePath}/halt_ime1_timing.gb",
            ],
            'if_ie_registers' => [
                'if_ie_registers.gb',
                "{$basePath}/if_ie_registers.gb",
            ],
            'intr_timing' => [
                'intr_timing.gb',
                "{$basePath}/intr_timing.gb",
            ],
            'jp_cc_timing' => [
                'jp_cc_timing.gb',
                "{$basePath}/jp_cc_timing.gb",
            ],
            'jp_timing' => [
                'jp_timing.gb',
                "{$basePath}/jp_timing.gb",
            ],
            'ld_hl_sp_e_timing' => [
                'ld_hl_sp_e_timing.gb',
                "{$basePath}/ld_hl_sp_e_timing.gb",
            ],
            'oam_dma_restart' => [
                'oam_dma_restart.gb',
                "{$basePath}/oam_dma_restart.gb",
            ],
            'oam_dma_start' => [
                'oam_dma_start.gb',
                "{$basePath}/oam_dma_start.gb",
            ],
            'oam_dma_timing' => [
                'oam_dma_timing.gb',
                "{$basePath}/oam_dma_timing.gb",
            ],
            'pop_timing' => [
                'pop_timing.gb',
                "{$basePath}/pop_timing.gb",
            ],
            'push_timing' => [
                'push_timing.gb',
                "{$basePath}/push_timing.gb",
            ],
            'rapid_di_ei' => [
                'rapid_di_ei.gb',
                "{$basePath}/rapid_di_ei.gb",
            ],
            'ret_cc_timing' => [
                'ret_cc_timing.gb',
                "{$basePath}/ret_cc_timing.gb",
            ],
            'ret_timing' => [
                'ret_timing.gb',
                "{$basePath}/ret_timing.gb",
            ],
            'reti_intr_timing' => [
                'reti_intr_timing.gb',
                "{$basePath}/reti_intr_timing.gb",
            ],
            'reti_timing' => [
                'reti_timing.gb',
                "{$basePath}/reti_timing.gb",
            ],
            'rst_timing' => [
                'rst_timing.gb',
                "{$basePath}/rst_timing.gb",
            ],

            // Instruction tests
            'instr/daa' => [
                'instr/daa.gb',
                "{$basePath}/instr/daa.gb",
            ],

            // Timer tests
            'timer/div_write' => [
                'timer/div_write.gb',
                "{$basePath}/timer/div_write.gb",
            ],
            'timer/rapid_toggle' => [
                'timer/rapid_toggle.gb',
                "{$basePath}/timer/rapid_toggle.gb",
            ],
            'timer/tim00' => [
                'timer/tim00.gb',
                "{$basePath}/timer/tim00.gb",
            ],
            'timer/tim00_div_trigger' => [
                'timer/tim00_div_trigger.gb',
                "{$basePath}/timer/tim00_div_trigger.gb",
            ],
            'timer/tim01' => [
                'timer/tim01.gb',
                "{$basePath}/timer/tim01.gb",
            ],
            'timer/tim01_div_trigger' => [
                'timer/tim01_div_trigger.gb',
                "{$basePath}/timer/tim01_div_trigger.gb",
            ],
            'timer/tim10' => [
                'timer/tim10.gb',
                "{$basePath}/timer/tim10.gb",
            ],
            'timer/tim10_div_trigger' => [
                'timer/tim10_div_trigger.gb',
                "{$basePath}/timer/tim10_div_trigger.gb",
            ],
            'timer/tim11' => [
                'timer/tim11.gb',
                "{$basePath}/timer/tim11.gb",
            ],
            'timer/tim11_div_trigger' => [
                'timer/tim11_div_trigger.gb',
                "{$basePath}/timer/tim11_div_trigger.gb",
            ],
            'timer/tima_reload' => [
                'timer/tima_reload.gb',
                "{$basePath}/timer/tima_reload.gb",
            ],
            'timer/tima_write_reloading' => [
                'timer/tima_write_reloading.gb',
                "{$basePath}/timer/tima_write_reloading.gb",
            ],
            'timer/tma_write_reloading' => [
                'timer/tma_write_reloading.gb',
                "{$basePath}/timer/tma_write_reloading.gb",
            ],
        ];
    }
}
