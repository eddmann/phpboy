<?php

declare(strict_types=1);

namespace Gb\Apu\Sink;

use Gb\Apu\AudioSinkInterface;

final class SoxAudioSink implements AudioSinkInterface
{
    /** @var resource|null */
    private $pipe = null;
    private string $playerName = 'play';
    private int $sampleRate;
    /** @var array<float> */
    private array $leftBuffer = [];
    /** @var array<float> */
    private array $rightBuffer = [];
    private int $bufferSize = 512;
    private int $droppedSamples = 0;

    public function __construct(int $sampleRate = 48000)
    {
        $this->sampleRate = $sampleRate;
        $this->openPipe();
    }

    public function __destruct()
    {
        $this->closePipe();
    }

    public function pushSample(float $left, float $right): void
    {
        if ($this->pipe === null) {
            return;
        }

        $this->leftBuffer[] = $left;
        $this->rightBuffer[] = $right;
    }

    public function flush(): void
    {
        if ($this->pipe === null || count($this->leftBuffer) === 0) {
            return;
        }

        if (!is_resource($this->pipe)) {
            error_log('SoX Audio: Pipe is no longer a valid resource');
            $this->pipe = null;
            $this->leftBuffer = [];
            $this->rightBuffer = [];
            return;
        }

        $interleavedData = '';
        for ($i = 0; $i < count($this->leftBuffer); $i++) {
            $left = max(-1.0, min(1.0, $this->leftBuffer[$i]));
            $right = max(-1.0, min(1.0, $this->rightBuffer[$i]));
            $interleavedData .= pack('ff', $left, $right);
        }

        $bytesWritten = fwrite($this->pipe, $interleavedData);

        if ($bytesWritten === false) {
            error_log('SoX Audio: Pipe broken');
            $this->pipe = null;
        }

        $this->leftBuffer = [];
        $this->rightBuffer = [];
    }

    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    public function isAvailable(): bool
    {
        return $this->pipe !== null;
    }

    private function openPipe(): void
    {
        $which = PHP_OS_FAMILY === 'Windows' ? 'where' : 'which';
        $checkCommand = "$which play 2>/dev/null";
        $result = @shell_exec($checkCommand);

        if (empty($result)) {
            error_log("Warning: SoX 'play' command not available");
            error_log("Install SoX for audio playback:");
            error_log("  macOS:   brew install sox");
            error_log("  Linux:   apt-get install sox");
            error_log("  Windows: https://sourceforge.net/projects/sox/");
            return;
        }

        $command = $this->buildSoxPlayCommand();
        $pipe = @popen($command, 'w');

        if ($pipe === false) {
            error_log("SoX Audio: Failed to open pipe to 'play' command");
            return;
        }

        stream_set_blocking($pipe, true);

        $this->pipe = $pipe;
    }

    private function closePipe(): void
    {
        if ($this->pipe === null) {
            return;
        }

        $this->flush();

        $pipe = $this->pipe;
        $this->pipe = null;

        if (is_resource($pipe)) {
            @pclose($pipe);
        }
    }

    private function buildSoxPlayCommand(): string
    {
        return sprintf(
            'play -q -t f32 -r %d -c 2 -b 32 --buffer 8192 - 2>/dev/null',
            $this->sampleRate
        );
    }

    public function setBufferSize(int $size): void
    {
        $this->bufferSize = max(128, $size);
    }

    public function getBufferSize(): int
    {
        return $this->bufferSize;
    }

    public function getDroppedSamples(): int
    {
        return $this->droppedSamples;
    }
}
