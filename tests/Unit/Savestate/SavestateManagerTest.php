<?php

declare(strict_types=1);

namespace Tests\Unit\Savestate;

use Gb\Emulator;
use Gb\Savestate\SavestateManager;
use PHPUnit\Framework\TestCase;

/**
 * SavestateManager Unit Tests
 *
 * Tests savestate serialization and deserialization functionality.
 */
final class SavestateManagerTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/phpboy_test_' . uniqid() . '.state';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    public function testSerializeReturnsValidStructure(): void
    {
        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../../third_party/roms/cpu_instrs/individual/01-special.gb');

        $manager = new SavestateManager($emulator);
        $state = $manager->serialize();

        // Verify structure
        $this->assertArrayHasKey('magic', $state);
        $this->assertArrayHasKey('version', $state);
        $this->assertArrayHasKey('cpu', $state);
        $this->assertArrayHasKey('ppu', $state);
        $this->assertArrayHasKey('memory', $state);
        $this->assertArrayHasKey('cartridge', $state);
        $this->assertArrayHasKey('clock', $state);

        $this->assertEquals('PHPBOY_SAVESTATE', $state['magic']);
        $this->assertEquals('1.0.0', $state['version']);
    }

    public function testSaveAndLoadState(): void
    {
        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../../third_party/roms/cpu_instrs/individual/01-special.gb');

        // Run for a few frames
        for ($i = 0; $i < 100; $i++) {
            $emulator->step();
        }

        // Get CPU state before saving
        $cpu = $emulator->getCpu();
        if ($cpu === null) {
            $this->fail('CPU is null');
        }
        $pcBefore = $cpu->getPC()->get();

        // Save state
        $emulator->saveState($this->tempFile);
        $this->assertFileExists($this->tempFile);

        // Run for more frames
        for ($i = 0; $i < 100; $i++) {
            $emulator->step();
        }

        // PC should have changed
        $pcAfter = $cpu->getPC()->get();
        $this->assertNotEquals($pcBefore, $pcAfter);

        // Load state
        $emulator->loadState($this->tempFile);

        // PC should be restored
        $pcRestored = $cpu->getPC()->get();
        $this->assertEquals($pcBefore, $pcRestored);
    }

    public function testSavestateFileFormat(): void
    {
        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../../third_party/roms/cpu_instrs/individual/01-special.gb');

        $emulator->saveState($this->tempFile);

        // Read file and verify it's valid JSON
        $json = file_get_contents($this->tempFile);
        $this->assertNotFalse($json);

        $data = json_decode($json, true);
        $this->assertNotNull($data);
        $this->assertIsArray($data);
    }

    public function testLoadNonExistentFileFails(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('not found');

        $emulator = new Emulator();
        $emulator->loadRom(__DIR__ . '/../../../third_party/roms/cpu_instrs/individual/01-special.gb');

        $emulator->loadState('/nonexistent/file.state');
    }
}
