<?php

declare(strict_types=1);

namespace Tests\Integration;

use Gb\Emulator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for savestate save/load functionality.
 *
 * Tests complete save/load cycles with actual emulation to verify
 * all state is properly preserved across different scenarios.
 */
final class SavestateIntegrationTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/phpboy_integration_test_' . uniqid() . '.state';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    #[Test]
    public function it_preserves_all_new_state_fields(): void
    {
        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../third_party/roms/cpu_instrs/individual/01-special.gb');

        // Run for several frames to establish state
        for ($i = 0; $i < 500; $i++) {
            $emulator->step();
        }

        // Capture state before saving
        $cpu = $emulator->getCpu();
        $timer = $emulator->getTimer();
        $interrupts = $emulator->getInterruptController();
        $cgb = $emulator->getCgbController();
        $clock = $emulator->getClock();

        $this->assertNotNull($cpu);
        $this->assertNotNull($timer);
        $this->assertNotNull($interrupts);

        $stateBefore = [
            'pc' => $cpu->getPC()->get(),
            'af' => $cpu->getAF()->get(),
            'bc' => $cpu->getBC()->get(),
            'de' => $cpu->getDE()->get(),
            'hl' => $cpu->getHL()->get(),
            'sp' => $cpu->getSP()->get(),
            'ime' => $cpu->getIME(),
            'div' => $timer->getDiv(),
            'divCounter' => $timer->getDivCounter(),
            'tima' => $timer->getTima(),
            'tma' => $timer->getTma(),
            'tac' => $timer->getTac(),
            'timaCounter' => $timer->getTimaCounter(),
            'if' => $interrupts->readByte(0xFF0F),
            'ie' => $interrupts->readByte(0xFFFF),
            'cycles' => $clock->getCycles(),
        ];

        // Save state
        $emulator->saveState($this->tempFile);

        // Run more to change state
        for ($i = 0; $i < 500; $i++) {
            $emulator->step();
        }

        // Verify state changed (DIV and cycles should always change, PC might not if halted)
        $this->assertNotEquals($stateBefore['cycles'], $clock->getCycles(), 'Cycles should have advanced');

        // Load state
        $emulator->loadState($this->tempFile);

        // Verify all state restored
        $this->assertEquals($stateBefore['pc'], $cpu->getPC()->get(), 'PC not restored');
        $this->assertEquals($stateBefore['af'], $cpu->getAF()->get(), 'AF not restored');
        $this->assertEquals($stateBefore['bc'], $cpu->getBC()->get(), 'BC not restored');
        $this->assertEquals($stateBefore['de'], $cpu->getDE()->get(), 'DE not restored');
        $this->assertEquals($stateBefore['hl'], $cpu->getHL()->get(), 'HL not restored');
        $this->assertEquals($stateBefore['sp'], $cpu->getSP()->get(), 'SP not restored');
        $this->assertEquals($stateBefore['ime'], $cpu->getIME(), 'IME not restored');
        $this->assertEquals($stateBefore['div'], $timer->getDiv(), 'DIV not restored');
        $this->assertEquals($stateBefore['divCounter'], $timer->getDivCounter(), 'DIV counter not restored');
        $this->assertEquals($stateBefore['tima'], $timer->getTima(), 'TIMA not restored');
        $this->assertEquals($stateBefore['tma'], $timer->getTma(), 'TMA not restored');
        $this->assertEquals($stateBefore['tac'], $timer->getTac(), 'TAC not restored');
        $this->assertEquals($stateBefore['timaCounter'], $timer->getTimaCounter(), 'TIMA counter not restored');
        $this->assertEquals($stateBefore['if'], $interrupts->readByte(0xFF0F), 'IF not restored');
        $this->assertEquals($stateBefore['ie'], $interrupts->readByte(0xFFFF), 'IE not restored');
        $this->assertEquals($stateBefore['cycles'], $clock->getCycles(), 'Clock cycles not restored');
    }

    #[Test]
    public function it_preserves_vram_banking_in_cgb_mode(): void
    {
        // Use a CGB-compatible ROM
        $emulator = new Emulator();
        $emulator->setHardwareMode('cgb');
        $emulator->loadRom(__DIR__ . '/../../third_party/roms/cpu_instrs/individual/01-special.gb');

        $ppu = $emulator->getPpu();
        $this->assertNotNull($ppu);

        $vram = $ppu->getVram();

        // Write test patterns to both VRAM banks
        $vram->setBank(0);
        for ($i = 0; $i < 100; $i++) {
            $vram->writeByte($i, 0xAA + $i);
        }

        $vram->setBank(1);
        for ($i = 0; $i < 100; $i++) {
            $vram->writeByte($i, 0xBB + $i);
        }

        $vram->setBank(0);

        // Save state
        $emulator->saveState($this->tempFile);

        // Corrupt VRAM
        $vram->setBank(0);
        for ($i = 0; $i < 100; $i++) {
            $vram->writeByte($i, 0x00);
        }
        $vram->setBank(1);
        for ($i = 0; $i < 100; $i++) {
            $vram->writeByte($i, 0x00);
        }

        // Load state
        $emulator->loadState($this->tempFile);

        // Verify current bank restored FIRST before we change it
        $this->assertEquals(0, $vram->getBank(), 'VRAM current bank not restored');

        // Verify both banks restored
        $vram->setBank(0);
        for ($i = 0; $i < 100; $i++) {
            $this->assertEquals((0xAA + $i) & 0xFF, $vram->readByte($i), "VRAM bank 0 byte $i not restored");
        }

        $vram->setBank(1);
        for ($i = 0; $i < 100; $i++) {
            $this->assertEquals((0xBB + $i) & 0xFF, $vram->readByte($i), "VRAM bank 1 byte $i not restored");
        }
    }

    #[Test]
    public function it_preserves_cgb_color_palettes(): void
    {
        $emulator = new Emulator();
        $emulator->setHardwareMode('cgb');
        $emulator->loadRom(__DIR__ . '/../../third_party/roms/cpu_instrs/individual/01-special.gb');

        $ppu = $emulator->getPpu();
        $this->assertNotNull($ppu);

        $colorPalette = $ppu->getColorPalette();

        // Set up test palette data
        $testBgPalette = array_fill(0, 64, 0);
        $testObjPalette = array_fill(0, 64, 0);

        for ($i = 0; $i < 64; $i++) {
            $testBgPalette[$i] = ($i * 3) & 0xFF;
            $testObjPalette[$i] = ($i * 5) & 0xFF;
        }

        $colorPalette->setBgPaletteMemory($testBgPalette);
        $colorPalette->setObjPaletteMemory($testObjPalette);
        $colorPalette->setBgIndexRaw(0x85);  // Index 5 with auto-increment
        $colorPalette->setObjIndexRaw(0x8A); // Index 10 with auto-increment

        // Save state
        $emulator->saveState($this->tempFile);

        // Corrupt palette data
        $colorPalette->setBgPaletteMemory(array_fill(0, 64, 0));
        $colorPalette->setObjPaletteMemory(array_fill(0, 64, 0));
        $colorPalette->setBgIndexRaw(0);
        $colorPalette->setObjIndexRaw(0);

        // Load state
        $emulator->loadState($this->tempFile);

        // Verify palettes restored
        $restoredBgPalette = $colorPalette->getBgPaletteMemory();
        $restoredObjPalette = $colorPalette->getObjPaletteMemory();

        for ($i = 0; $i < 64; $i++) {
            $this->assertEquals($testBgPalette[$i], $restoredBgPalette[$i], "BG palette byte $i not restored");
            $this->assertEquals($testObjPalette[$i], $restoredObjPalette[$i], "OBJ palette byte $i not restored");
        }

        $this->assertEquals(0x85, $colorPalette->getBgIndexRaw(), 'BG palette index not restored');
        $this->assertEquals(0x8A, $colorPalette->getObjIndexRaw(), 'OBJ palette index not restored');
    }

    #[Test]
    public function it_preserves_cgb_controller_state(): void
    {
        $emulator = new Emulator();
        $emulator->setHardwareMode('cgb');
        $emulator->loadRom(__DIR__ . '/../../third_party/roms/cpu_instrs/individual/01-special.gb');

        $cgb = $emulator->getCgbController();
        $this->assertNotNull($cgb);

        // Set up CGB controller state
        $cgb->setKey0(0x80);
        $cgb->setKey1(0x01);
        $cgb->setOpri(0x01);
        $cgb->setDoubleSpeed(false);
        $cgb->setKey0Writable(false);

        // Save state
        $emulator->saveState($this->tempFile);

        // Change state
        $cgb->setKey0(0x04);
        $cgb->setKey1(0x00);
        $cgb->setOpri(0x00);
        $cgb->setDoubleSpeed(true);
        $cgb->setKey0Writable(true);

        // Load state
        $emulator->loadState($this->tempFile);

        // Verify restoration
        $this->assertEquals(0x80, $cgb->getKey0(), 'KEY0 not restored');
        $this->assertEquals(0x01, $cgb->getKey1(), 'KEY1 not restored');
        $this->assertEquals(0x01, $cgb->getOpri(), 'OPRI not restored');
        $this->assertEquals(false, $cgb->isDoubleSpeed(), 'Double speed not restored');
        $this->assertEquals(false, $cgb->isKey0Writable(), 'KEY0 writable not restored');
    }

    #[Test]
    public function it_preserves_apu_state(): void
    {
        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../third_party/roms/cpu_instrs/individual/01-special.gb');

        $apu = $emulator->getApu();
        $this->assertNotNull($apu);

        // Run to establish APU state
        for ($i = 0; $i < 1000; $i++) {
            $emulator->step();
        }

        // Capture APU state before save
        $waveRamBefore = $apu->getWaveRam();
        $frameSeqCyclesBefore = $apu->getFrameSequencerCycles();
        $frameSeqStepBefore = $apu->getFrameSequencerStep();
        $sampleCyclesBefore = $apu->getSampleCycles();
        $enabledBefore = $apu->isEnabled();

        // Write test pattern to Wave RAM
        $testWaveRam = [];
        for ($i = 0; $i < 16; $i++) {
            $testWaveRam[$i] = ($i * 17) & 0xFF;
        }
        $apu->setWaveRam($testWaveRam);

        // Save state
        $emulator->saveState($this->tempFile);

        // Corrupt APU state
        $apu->setWaveRam(array_fill(0, 16, 0));
        $apu->setFrameSequencerCycles(0);
        $apu->setFrameSequencerStep(0);
        $apu->setSampleCycles(0.0);

        // Load state
        $emulator->loadState($this->tempFile);

        // Verify APU state restored
        $waveRamAfter = $apu->getWaveRam();
        for ($i = 0; $i < 16; $i++) {
            $this->assertEquals($testWaveRam[$i], $waveRamAfter[$i], "Wave RAM byte $i not restored");
        }

        $this->assertEquals($frameSeqCyclesBefore, $apu->getFrameSequencerCycles(), 'Frame sequencer cycles not restored');
        $this->assertEquals($frameSeqStepBefore, $apu->getFrameSequencerStep(), 'Frame sequencer step not restored');
        $this->assertEquals($sampleCyclesBefore, $apu->getSampleCycles(), 'Sample cycles not restored');
        $this->assertEquals($enabledBefore, $apu->isEnabled(), 'APU enabled state not restored');
    }

    #[Test]
    public function it_supports_multiple_save_load_cycles(): void
    {
        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../third_party/roms/cpu_instrs/individual/01-special.gb');

        $cpu = $emulator->getCpu();
        $this->assertNotNull($cpu);

        $savedStates = [];

        // Create multiple savepoints
        for ($savepoint = 0; $savepoint < 3; $savepoint++) {
            // Run for some frames
            for ($i = 0; $i < 200; $i++) {
                $emulator->step();
            }

            // Save current state
            $savedStates[$savepoint] = [
                'pc' => $cpu->getPC()->get(),
                'af' => $cpu->getAF()->get(),
                'file' => sys_get_temp_dir() . "/phpboy_test_savepoint_{$savepoint}_" . uniqid() . '.state',
            ];

            $emulator->saveState($savedStates[$savepoint]['file']);
        }

        // Run more to establish different state
        for ($i = 0; $i < 500; $i++) {
            $emulator->step();
        }

        // Load and verify each savepoint in reverse order
        for ($savepoint = 2; $savepoint >= 0; $savepoint--) {
            $emulator->loadState($savedStates[$savepoint]['file']);

            $this->assertEquals(
                $savedStates[$savepoint]['pc'],
                $cpu->getPC()->get(),
                "PC mismatch at savepoint $savepoint"
            );
            $this->assertEquals(
                $savedStates[$savepoint]['af'],
                $cpu->getAF()->get(),
                "AF mismatch at savepoint $savepoint"
            );

            // Clean up
            unlink($savedStates[$savepoint]['file']);
        }
    }

    #[Test]
    public function it_preserves_memory_contents(): void
    {
        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../third_party/roms/cpu_instrs/individual/01-special.gb');

        $bus = $emulator->getBus();
        $this->assertNotNull($bus);

        // Write test patterns to various memory regions
        // WRAM
        for ($i = 0; $i < 100; $i++) {
            $bus->writeByte(0xC000 + $i, ($i * 7) & 0xFF);
        }

        // HRAM
        for ($i = 0; $i < 100; $i++) {
            $bus->writeByte(0xFF80 + $i, ($i * 11) & 0xFF);
        }

        // Save state
        $emulator->saveState($this->tempFile);

        // Corrupt memory
        for ($i = 0; $i < 100; $i++) {
            $bus->writeByte(0xC000 + $i, 0);
            $bus->writeByte(0xFF80 + $i, 0);
        }

        // Load state
        $emulator->loadState($this->tempFile);

        // Verify memory restored
        for ($i = 0; $i < 100; $i++) {
            $this->assertEquals(
                ($i * 7) & 0xFF,
                $bus->readByte(0xC000 + $i),
                "WRAM byte at 0xC000+$i not restored"
            );
            $this->assertEquals(
                ($i * 11) & 0xFF,
                $bus->readByte(0xFF80 + $i),
                "HRAM byte at 0xFF80+$i not restored"
            );
        }
    }

    #[Test]
    public function it_preserves_state_across_rom_reload(): void
    {
        $romPath = __DIR__ . '/../../third_party/roms/cpu_instrs/individual/01-special.gb';

        // First emulator instance
        $emulator1 = new Emulator();
        $emulator1->loadRom($romPath);

        // Run to establish state
        for ($i = 0; $i < 500; $i++) {
            $emulator1->step();
        }

        $cpu1 = $emulator1->getCpu();
        $this->assertNotNull($cpu1);
        $pcBefore = $cpu1->getPC()->get();

        // Save state
        $emulator1->saveState($this->tempFile);

        // Create new emulator instance and load same ROM
        $emulator2 = new Emulator();
        $emulator2->loadRom($romPath);

        // Load savestate
        $emulator2->loadState($this->tempFile);

        // Verify state transferred to new emulator instance
        $cpu2 = $emulator2->getCpu();
        $this->assertNotNull($cpu2);
        $this->assertEquals($pcBefore, $cpu2->getPC()->get(), 'PC not preserved across emulator instances');
    }

    #[Test]
    public function it_contains_all_expected_json_fields(): void
    {
        $emulator = new Emulator();
        $emulator->setHardwareMode('cgb');
        $emulator->loadRom(__DIR__ . '/../../third_party/roms/cpu_instrs/individual/01-special.gb');

        // Run to populate all state
        for ($i = 0; $i < 500; $i++) {
            $emulator->step();
        }

        $emulator->saveState($this->tempFile);

        $json = file_get_contents($this->tempFile);
        $this->assertNotFalse($json);

        $state = json_decode($json, true);
        $this->assertIsArray($state);

        // Verify all top-level fields exist
        $this->assertArrayHasKey('magic', $state);
        $this->assertArrayHasKey('version', $state);
        $this->assertArrayHasKey('timestamp', $state);
        $this->assertArrayHasKey('cpu', $state);
        $this->assertArrayHasKey('ppu', $state);
        $this->assertArrayHasKey('memory', $state);
        $this->assertArrayHasKey('cartridge', $state);
        $this->assertArrayHasKey('timer', $state);
        $this->assertArrayHasKey('interrupts', $state);
        $this->assertArrayHasKey('cgb', $state);
        $this->assertArrayHasKey('apu', $state);
        $this->assertArrayHasKey('clock', $state);

        // Verify new field structures
        $this->assertIsArray($state['timer']);
        $this->assertArrayHasKey('div', $state['timer']);
        $this->assertArrayHasKey('divCounter', $state['timer']);
        $this->assertArrayHasKey('tima', $state['timer']);

        $this->assertIsArray($state['interrupts']);
        $this->assertArrayHasKey('if', $state['interrupts']);
        $this->assertArrayHasKey('ie', $state['interrupts']);

        $this->assertIsArray($state['cgb']);
        $this->assertArrayHasKey('key0', $state['cgb']);
        $this->assertArrayHasKey('key1', $state['cgb']);
        $this->assertArrayHasKey('doubleSpeed', $state['cgb']);

        $this->assertIsArray($state['apu']);
        $this->assertArrayHasKey('registers', $state['apu']);
        $this->assertArrayHasKey('waveRam', $state['apu']);
        $this->assertArrayHasKey('frameSequencerCycles', $state['apu']);

        $this->assertIsArray($state['ppu']);
        $this->assertArrayHasKey('cgbPalette', $state['ppu']);

        $this->assertIsArray($state['memory']);
        $this->assertArrayHasKey('vramBank0', $state['memory']);
        $this->assertArrayHasKey('vramBank1', $state['memory']);
        $this->assertArrayHasKey('vramCurrentBank', $state['memory']);
    }
}
