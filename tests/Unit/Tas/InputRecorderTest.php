<?php

declare(strict_types=1);

namespace Tests\Unit\Tas;

use Gb\Input\Button;
use Gb\Tas\InputRecorder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * InputRecorder Unit Tests
 *
 * Tests TAS recording and playback functionality.
 */
final class InputRecorderTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = sys_get_temp_dir() . '/phpboy_tas_test_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    #[Test]
    public function it_records_and_plays_back(): void
    {
        $recorder = new InputRecorder();
        $recorder->startRecording();

        $this->assertTrue($recorder->isRecording());

        // Record some inputs
        $recorder->recordFrame([Button::A, Button::Start]);
        $recorder->recordFrame([Button::Right]);
        $recorder->recordFrame([]);

        $recorder->stopRecording();
        $this->assertFalse($recorder->isRecording());

        // Save recording
        $recorder->saveRecording($this->tempFile);
        $this->assertFileExists($this->tempFile);

        // Load and playback
        $playback = new InputRecorder();
        $playback->loadRecording($this->tempFile);
        $playback->startPlayback();

        $this->assertTrue($playback->isPlaying());

        // Verify playback matches recording
        $frame0 = $playback->getPlaybackInputs();
        $this->assertCount(2, $frame0);
        $this->assertContains(Button::A, $frame0);
        $this->assertContains(Button::Start, $frame0);

        $frame1 = $playback->getPlaybackInputs();
        $this->assertCount(1, $frame1);
        $this->assertContains(Button::Right, $frame1);

        $frame2 = $playback->getPlaybackInputs();
        $this->assertCount(0, $frame2);
    }

    #[Test]
    public function it_uses_valid_recording_format(): void
    {
        $recorder = new InputRecorder();
        $recorder->startRecording();

        $recorder->recordFrame([Button::A]);
        $recorder->recordFrame([Button::B, Button::Start]);

        $recorder->stopRecording();
        $recorder->saveRecording($this->tempFile);

        // Read file and verify format
        $json = file_get_contents($this->tempFile);
        $this->assertNotFalse($json);

        $data = json_decode($json, true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('version', $data);
        $this->assertArrayHasKey('frames', $data);
        $this->assertArrayHasKey('inputs', $data);
        $this->assertEquals('1.0', (string) $data['version']);
    }

    #[Test]
    public function it_cannot_save_while_recording(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('recording is active');

        $recorder = new InputRecorder();
        $recorder->startRecording();
        $recorder->saveRecording($this->tempFile);
    }

    #[Test]
    public function it_detects_when_playback_is_finished(): void
    {
        $recorder = new InputRecorder();
        $recorder->startRecording();
        $recorder->recordFrame([Button::A]);
        $recorder->stopRecording();
        $recorder->saveRecording($this->tempFile);

        $playback = new InputRecorder();
        $playback->loadRecording($this->tempFile);
        $playback->startPlayback();

        $this->assertFalse($playback->isPlaybackFinished());

        // Get the only frame
        $playback->getPlaybackInputs();

        $this->assertTrue($playback->isPlaybackFinished());
    }
}
