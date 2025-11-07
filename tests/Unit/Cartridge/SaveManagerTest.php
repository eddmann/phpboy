<?php

declare(strict_types=1);

namespace Tests\Unit\Cartridge;

use Gb\Cartridge\SaveManager;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SaveManagerTest extends TestCase
{
    private SaveManager $saveManager;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->saveManager = new SaveManager();
        $this->tempDir = sys_get_temp_dir() . '/phpboy_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Clean up temp files
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if ($files !== false) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        unlink($file);
                    }
                }
            }
            rmdir($this->tempDir);
        }
    }

    public function testSaveAndLoadRam(): void
    {
        $path = $this->tempDir . '/test.sav';
        $ram = array_fill(0, 8192, 0x00);

        // Set some test data
        $ram[0] = 0x12;
        $ram[100] = 0x34;
        $ram[8191] = 0xFF;

        // Save
        $this->saveManager->saveRam($path, $ram);
        $this->assertFileExists($path);

        // Load
        $loaded = $this->saveManager->loadRam($path, 8192);
        $this->assertSame($ram, $loaded);
    }

    public function testLoadNonExistentRam(): void
    {
        $path = $this->tempDir . '/nonexistent.sav';

        // Should return empty RAM if file doesn't exist
        $loaded = $this->saveManager->loadRam($path, 8192);
        $this->assertSame(array_fill(0, 8192, 0x00), $loaded);
    }

    public function testLoadRamPadding(): void
    {
        $path = $this->tempDir . '/test.sav';

        // Save 4KB
        $ram = array_fill(0, 4096, 0x42);
        $this->saveManager->saveRam($path, $ram);

        // Load as 8KB (should be padded)
        $loaded = $this->saveManager->loadRam($path, 8192);
        $this->assertSame(8192, count($loaded));

        // First 4KB should be 0x42
        for ($i = 0; $i < 4096; $i++) {
            $this->assertSame(0x42, $loaded[$i]);
        }

        // Remaining should be 0x00
        for ($i = 4096; $i < 8192; $i++) {
            $this->assertSame(0x00, $loaded[$i]);
        }
    }

    public function testLoadRamTruncation(): void
    {
        $path = $this->tempDir . '/test.sav';

        // Save 8KB
        $ram = array_fill(0, 8192, 0x42);
        $this->saveManager->saveRam($path, $ram);

        // Load as 4KB (should be truncated)
        $loaded = $this->saveManager->loadRam($path, 4096);
        $this->assertSame(4096, count($loaded));

        for ($i = 0; $i < 4096; $i++) {
            $this->assertSame(0x42, $loaded[$i]);
        }
    }

    public function testSaveAndLoadRtc(): void
    {
        $path = $this->tempDir . '/test.rtc';
        $rtcState = [
            'seconds' => 30,
            'minutes' => 45,
            'hours' => 12,
            'days' => 100,
            'halt' => 0,
            'dayHigh' => 0x00,
        ];

        // Save
        $this->saveManager->saveRtc($path, $rtcState);
        $this->assertFileExists($path);

        // Load immediately (no time elapsed)
        $loaded = $this->saveManager->loadRtc($path);
        $this->assertNotNull($loaded);
        $this->assertSame(30, $loaded['seconds']);
        $this->assertSame(45, $loaded['minutes']);
        $this->assertSame(12, $loaded['hours']);
        $this->assertSame(100, $loaded['days']);
    }

    public function testLoadNonExistentRtc(): void
    {
        $path = $this->tempDir . '/nonexistent.rtc';

        $loaded = $this->saveManager->loadRtc($path);
        $this->assertNull($loaded);
    }

    public function testRtcTimeElapsed(): void
    {
        $path = $this->tempDir . '/test.rtc';
        $rtcState = [
            'seconds' => 30,
            'minutes' => 45,
            'hours' => 12,
            'days' => 100,
            'halt' => 0,
            'dayHigh' => 0x00,
        ];

        // Manually create RTC file with old timestamp
        $data = [
            'timestamp' => time() - 65, // 65 seconds ago
            'rtc' => $rtcState,
        ];
        file_put_contents($path, json_encode($data));

        // Load - should add elapsed time
        $loaded = $this->saveManager->loadRtc($path);
        $this->assertNotNull($loaded);

        // 30 + 65 = 95 seconds, which is 1 minute 35 seconds
        $this->assertSame(35, $loaded['seconds']); // 95 % 60 = 35
        $this->assertSame(46, $loaded['minutes']); // 45 + 1 = 46
        $this->assertSame(12, $loaded['hours']);
        $this->assertSame(100, $loaded['days']);
    }

    public function testRtcTimeElapsedWithDayOverflow(): void
    {
        $path = $this->tempDir . '/test.rtc';
        $rtcState = [
            'seconds' => 0,
            'minutes' => 0,
            'hours' => 23,
            'days' => 511, // Max days
            'halt' => 0,
            'dayHigh' => 0x00,
        ];

        // Manually create RTC file with old timestamp (2 hours ago)
        $data = [
            'timestamp' => time() - 7200, // 2 hours = 7200 seconds
            'rtc' => $rtcState,
        ];
        file_put_contents($path, json_encode($data));

        // Load - should overflow days
        $loaded = $this->saveManager->loadRtc($path);
        $this->assertNotNull($loaded);

        $this->assertSame(0, $loaded['seconds']);
        $this->assertSame(0, $loaded['minutes']);
        $this->assertSame(1, $loaded['hours']); // 23 + 2 = 25, 25 % 24 = 1
        $this->assertSame(0, $loaded['days']); // 511 + 1 = 512, overflow to 0
        $this->assertSame(0x80, $loaded['dayHigh'] & 0x80); // Carry flag set
    }

    public function testRtcHaltedDoesNotAdvance(): void
    {
        $path = $this->tempDir . '/test.rtc';
        $rtcState = [
            'seconds' => 30,
            'minutes' => 45,
            'hours' => 12,
            'days' => 100,
            'halt' => 1, // Halted
            'dayHigh' => 0x40, // Halt flag set
        ];

        // Manually create RTC file with old timestamp
        $data = [
            'timestamp' => time() - 3600, // 1 hour ago
            'rtc' => $rtcState,
        ];
        file_put_contents($path, json_encode($data));

        // Load - should NOT advance because halted
        $loaded = $this->saveManager->loadRtc($path);
        $this->assertNotNull($loaded);

        $this->assertSame(30, $loaded['seconds']);
        $this->assertSame(45, $loaded['minutes']);
        $this->assertSame(12, $loaded['hours']);
        $this->assertSame(100, $loaded['days']);
    }

    public function testGetSavePath(): void
    {
        $this->assertSame('test.sav', $this->saveManager->getSavePath('test.gb'));
        $this->assertSame('test.sav', $this->saveManager->getSavePath('test.gbc'));
        $this->assertSame('path/to/test.sav', $this->saveManager->getSavePath('path/to/test.gb'));
    }

    public function testGetRtcPath(): void
    {
        $this->assertSame('test.rtc', $this->saveManager->getRtcPath('test.gb'));
        $this->assertSame('test.rtc', $this->saveManager->getRtcPath('test.gbc'));
        $this->assertSame('path/to/test.rtc', $this->saveManager->getRtcPath('path/to/test.gb'));
    }

    public function testSaveExists(): void
    {
        $path = $this->tempDir . '/test.sav';

        $this->assertFalse($this->saveManager->saveExists($path));

        file_put_contents($path, 'test');

        $this->assertTrue($this->saveManager->saveExists($path));
    }

    public function testDeleteSave(): void
    {
        $path = $this->tempDir . '/test.sav';

        file_put_contents($path, 'test');
        $this->assertTrue(file_exists($path));

        $result = $this->saveManager->deleteSave($path);
        $this->assertTrue($result);
        $this->assertFalse(file_exists($path));

        // Try deleting non-existent file
        $result = $this->saveManager->deleteSave($path);
        $this->assertFalse($result);
    }

    public function testSaveEmptyRam(): void
    {
        $path = $this->tempDir . '/test.sav';

        // Saving empty RAM should do nothing
        $this->saveManager->saveRam($path, []);
        $this->assertFileDoesNotExist($path);
    }
}
