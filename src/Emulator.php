<?php

declare(strict_types=1);

namespace Gb;

use Gb\Apu\Apu;
use Gb\Apu\AudioSinkInterface;
use Gb\Apu\Sink\NullSink;
use Gb\Bus\SystemBus;
use Gb\Cartridge\Cartridge;
use Gb\Clock\Clock;
use Gb\Cpu\Cpu;
use Gb\Dma\HdmaController;
use Gb\Dma\OamDma;
use Gb\Input\InputInterface;
use Gb\Input\Joypad;
use Gb\Interrupts\InterruptController;
use Gb\Memory\Hram;
use Gb\Memory\Vram;
use Gb\Memory\Wram;
use Gb\Ppu\ArrayFramebuffer;
use Gb\Ppu\ColorPalette;
use Gb\Ppu\DmgColorizer;
use Gb\Ppu\FramebufferInterface;
use Gb\Ppu\Oam;
use Gb\Ppu\Ppu;
use Gb\Serial\Serial;
use Gb\System\CgbController;
use Gb\Timer\Timer;

/**
 * Main PHPBoy Emulator Coordinator
 *
 * Orchestrates all Game Boy subsystems (CPU, PPU, APU, timers, input, etc.)
 * and provides the main emulation loop.
 *
 * Frame timing: 70224 CPU cycles per frame at 59.7 Hz
 * CPU speed: 4.194304 MHz (DMG) or 8.388608 MHz (CGB double-speed)
 *
 * Usage:
 *   $emulator = new Emulator();
 *   $emulator->setHardwareMode('dmg'); // Optional: force DMG or CGB mode
 *   $emulator->loadRom('/path/to/rom.gb');
 *   $emulator->setInput($inputHandler);
 *   $emulator->run(); // or step() for single frame
 */
final class Emulator
{
    private const CYCLES_PER_FRAME = 70224;
    private const TARGET_FPS = 59.7;

    private ?Cartridge $cartridge = null;
    private ?Cpu $cpu = null;
    private ?Ppu $ppu = null;
    private ?Apu $apu = null;
    private ?SystemBus $bus = null;

    private Clock $clock;
    private bool $running = false;
    private bool $paused = false;
    private float $speedMultiplier = 1.0;

    private ?InputInterface $input = null;
    private FramebufferInterface $framebuffer;
    private AudioSinkInterface $audioSink;

    /** @var string|null Manual DMG palette selection (e.g., 'grayscale', 'left_b') */
    private ?string $dmgPalette = null;

    /** @var string|null Force hardware mode: 'dmg', 'cgb', or null for auto-detect */
    private ?string $forcedHardwareMode = null;

    // Subsystems
    private ?InterruptController $interruptController = null;
    private ?Timer $timer = null;
    private ?OamDma $oamDma = null;
    private ?HdmaController $hdma = null;
    private ?CgbController $cgb = null;
    private ?Joypad $joypad = null;
    private ?Serial $serial = null;

    public function __construct()
    {
        $this->clock = new Clock();
        $this->framebuffer = new ArrayFramebuffer();
        $this->audioSink = new NullSink();
    }

    /**
     * Load a ROM file and initialize the emulator.
     *
     * @param string $path Path to the ROM file
     * @throws \RuntimeException If ROM file cannot be loaded
     */
    public function loadRom(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("ROM file not found: {$path}");
        }

        $romData = file_get_contents($path);
        if ($romData === false) {
            throw new \RuntimeException("Failed to read ROM file: {$path}");
        }

        // Convert string to byte array
        $unpacked = unpack('C*', $romData);
        if ($unpacked === false) {
            throw new \RuntimeException("Failed to unpack ROM data");
        }
        $romBytes = array_values($unpacked);
        $this->cartridge = Cartridge::fromRom($romBytes);
        $this->initializeSystem();
    }

    /**
     * Initialize all Game Boy subsystems.
     */
    private function initializeSystem(): void
    {
        if ($this->cartridge === null) {
            throw new \RuntimeException("Cannot initialize system: no cartridge loaded");
        }

        $isCgbMode = $this->forcedHardwareMode !== null
            ? ($this->forcedHardwareMode === 'cgb')
            : $this->cartridge->getHeader()->isCgbSupported();

        // Create interrupt controller
        $this->interruptController = new InterruptController();

        // Create memory regions
        $vram = new Vram();
        $wram = new Wram();
        $oam = new Oam();
        $hram = new Hram();

        // Create PPU
        $this->ppu = new Ppu(
            $vram,
            $oam,
            $this->framebuffer,
            $this->interruptController
        );

        $this->configurePpuDisplayMode($isCgbMode);

        // Create APU
        $this->apu = new Apu($this->audioSink);

        // Create system bus
        $this->bus = new SystemBus();

        // Attach memory devices
        assert($this->cartridge !== null);
        $this->bus->attachDevice('cartridge', $this->cartridge, 0x0000, 0x7FFF);
        $this->bus->attachDevice('cartridge', $this->cartridge, 0xA000, 0xBFFF); // External RAM
        $this->bus->attachDevice('vram', $vram, 0x8000, 0x9FFF);
        $this->bus->attachDevice('wram', $wram, 0xC000, 0xDFFF);
        $this->bus->attachDevice('oam', $oam, 0xFE00, 0xFE9F);
        $this->bus->attachDevice('hram', $hram, 0xFF80, 0xFFFE);

        // Attach I/O devices (PPU, APU, Timer, etc.)
        // PPU registers: LCDC, STAT, SCY, SCX, LY, LYC, BGP, OBP0, OBP1, WY, WX, BCPS, BCPD, OCPS, OCPD
        assert($this->ppu !== null);
        $this->bus->attachIoDevice(
            $this->ppu,
            0xFF40, 0xFF41, 0xFF42, 0xFF43, 0xFF44, 0xFF45, 0xFF47, 0xFF48, 0xFF49, 0xFF4A, 0xFF4B,
            0xFF68, 0xFF69, 0xFF6A, 0xFF6B
        );

        // APU registers: NR10-NR52, Wave RAM
        $apuRegisters = array_merge(
            range(0xFF10, 0xFF14), // Channel 1
            range(0xFF16, 0xFF19), // Channel 2
            range(0xFF1A, 0xFF1E), // Channel 3
            range(0xFF20, 0xFF23), // Channel 4
            range(0xFF24, 0xFF26), // Control/Status
            range(0xFF30, 0xFF3F)  // Wave RAM
        );
        $this->bus->attachIoDevice($this->apu, ...$apuRegisters);

        // Create timer
        assert($this->interruptController !== null);
        $interruptController = $this->interruptController; // Narrow type for PHPStan
        $this->timer = new Timer($interruptController);
        // Timer registers: DIV, TIMA, TMA, TAC
        $this->bus->attachIoDevice($this->timer, 0xFF04, 0xFF05, 0xFF06, 0xFF07);

        // Create DMA controllers
        $this->oamDma = new OamDma($this->bus);
        $this->bus->attachIoDevice($this->oamDma, 0xFF46); // OAM DMA register

        $this->hdma = new HdmaController($this->bus);
        // HDMA registers: HDMA1-HDMA5
        $this->bus->attachIoDevice($this->hdma, 0xFF51, 0xFF52, 0xFF53, 0xFF54, 0xFF55);

        // Create CGB controller
        $this->cgb = new CgbController($vram, $wram, $isCgbMode);
        // CGB registers: KEY0, KEY1, VBK, RP, OPRI, SVBK
        $this->bus->attachIoDevice($this->cgb, 0xFF4C, 0xFF4D, 0xFF4F, 0xFF56, 0xFF6C, 0xFF70);

        // Create joypad
        $this->joypad = new Joypad($interruptController);
        $this->bus->attachIoDevice($this->joypad, 0xFF00); // JOYP register

        // Create serial
        $this->serial = new Serial($interruptController);
        // Serial registers: SB, SC
        $this->bus->attachIoDevice($this->serial, 0xFF01, 0xFF02);

        // Attach interrupt controller
        $this->bus->attachIoDevice($interruptController, 0xFF0F, 0xFFFF); // IF and IE registers

        // Create CPU
        $this->cpu = new Cpu($this->bus, $interruptController);

        // Initialize CPU registers to post-boot ROM values
        // This simulates the state after the boot ROM has finished
        if ($isCgbMode) {
            // CGB mode register values (Pan Docs - Power Up Sequence)
            $this->cpu->setAF(0x1180); // A=0x11 (CGB identifier), F=0x80 (Z flag set)
            $this->cpu->setBC(0x0000); // B=0x00, C=0x00
            $this->cpu->setDE(0xFF56); // D=0xFF, E=0x56
            $this->cpu->setHL(0x000D); // H=0x00, L=0x0D
        } else {
            // DMG mode register values
            $this->cpu->setAF(0x01B0); // A=0x01 (DMG identifier), F=0xB0 (Z=1, H=1, C=1)
            $this->cpu->setBC(0x0013); // B=0x00, C=0x13
            $this->cpu->setDE(0x00D8); // D=0x00, E=0xD8
            $this->cpu->setHL(0x014D); // H=0x01, L=0x4D
        }
        $this->cpu->setSP(0xFFFE); // Stack pointer
        $this->cpu->setPC(0x0100); // Start at cartridge entry point

        // Optimization (Step 14): Pre-build all 512 instructions for faster dispatch
        // Expected: 1-2% performance gain by eliminating lazy initialization checks
        \Gb\Cpu\InstructionSet::warmCache();

        // Set up M-cycle accurate callback
        // This callback is invoked by the CPU during instruction execution
        // to advance all other components in real-time
        $this->cpu->setCycleCallback(function (int $cycles) {
            $this->ppu?->step($cycles);
            $this->apu?->step($cycles);
            $this->timer?->tick($cycles);
            $this->oamDma?->tick($cycles);
            $this->clock->tick($cycles);
        });

        // Reset clock
        $this->clock->reset();
    }

    private function configurePpuDisplayMode(bool $isCgbMode): void
    {
        if ($this->ppu === null) {
            return;
        }

        // Forced DMG hardware mode → grayscale
        if ($this->forcedHardwareMode === 'dmg') {
            $this->ppu->enableDmgCompatibilityMode(false);
            $this->ppu->enableCgbMode(false);
            return;
        }

        // Manual palette → DMG colorization
        if ($this->dmgPalette !== null) {
            $paletteApplied = $this->applyDmgColorization();
            $this->ppu->enableDmgCompatibilityMode($paletteApplied);
            if (!$paletteApplied) {
                $this->ppu->enableCgbMode(false);
            }
            return;
        }

        // CGB-compatible cartridge → native CGB mode
        if ($isCgbMode) {
            $this->ppu->enableCgbMode(true);
            return;
        }

        // DMG-only game → auto-detect colorization or fallback to grayscale
        $paletteApplied = $this->applyDmgColorization();
        $this->ppu->enableDmgCompatibilityMode($paletteApplied);
        if (!$paletteApplied) {
            $this->ppu->enableCgbMode(false);
        }
    }

    /**
     * Apply DMG colorization to simulate CGB boot ROM behavior.
     *
     * When a DMG-only game runs on CGB hardware, the boot ROM automatically
     * applies color palettes based on game detection. This method replicates
     * that behavior.
     *
     * @return bool True if colorization was applied, false otherwise
     */
    private function applyDmgColorization(): bool
    {
        if ($this->cartridge === null || $this->ppu === null) {
            return false;
        }

        $header = $this->cartridge->getHeader();

        // Get the PPU's color palette
        $colorPalette = $this->ppu->getColorPalette();

        // Create colorizer
        $colorizer = new DmgColorizer($colorPalette);

        // Use manual palette if set, otherwise check for automatic detection
        $buttonCombo = $this->dmgPalette;

        // If manual palette is set, always apply it (even for CGB games)
        if ($buttonCombo !== null) {
            $colorizer->colorize($header, $buttonCombo);
            return true;
        }

        // For automatic detection, only colorize DMG-only games
        if (!$header->isDmgOnly()) {
            return false;
        }

        // Check if game has an automatic palette via checksum
        $checksum = $colorizer->calculateTitleChecksum($header);
        $paletteName = \Gb\Ppu\DmgPalettes::getPaletteNameByChecksum($checksum);

        // Only apply colorization if game is detected
        if ($paletteName !== null) {
            $colorizer->colorize($header, null);
            return true;
        }

        return false;
    }

    /**
     * Set the input handler.
     */
    public function setInput(InputInterface $input): void
    {
        $this->input = $input;
    }

    /**
     * Set the framebuffer for rendering.
     */
    public function setFramebuffer(FramebufferInterface $framebuffer): void
    {
        $this->framebuffer = $framebuffer;
        if ($this->ppu !== null) {
            // PPU is already initialized, need to reinitialize
            $this->initializeSystem();
        }
    }

    /**
     * Set the audio sink for audio output.
     */
    public function setAudioSink(AudioSinkInterface $audioSink): void
    {
        $this->audioSink = $audioSink;
        if ($this->apu !== null) {
            // APU is already initialized, need to reinitialize
            $this->initializeSystem();
        }
    }

    /**
     * Set the emulation speed multiplier.
     *
     * @param float $multiplier Speed multiplier (1.0 = normal, 2.0 = 2x speed, etc.)
     */
    public function setSpeed(float $multiplier): void
    {
        $this->speedMultiplier = max(0.1, $multiplier);
    }

    /**
     * Set the DMG palette for colorization.
     *
     * @param string $palette Palette name (e.g., 'grayscale', 'left_b')
     */
    public function setDmgPalette(string $palette): void
    {
        $this->dmgPalette = $palette;

        // Re-initialize system if already loaded
        if ($this->cartridge !== null) {
            $this->initializeSystem();
        }
    }

    /**
     * Force a specific hardware mode regardless of cartridge header.
     *
     * @param string|null $mode 'dmg' for Game Boy, 'cgb' for Game Boy Color, null for auto-detect
     * @throws \InvalidArgumentException If mode is not 'dmg', 'cgb', or null
     */
    public function setHardwareMode(?string $mode): void
    {
        if ($mode !== null && $mode !== 'dmg' && $mode !== 'cgb') {
            throw new \InvalidArgumentException("Hardware mode must be 'dmg', 'cgb', or null");
        }

        $this->forcedHardwareMode = $mode;

        // Re-initialize system if already loaded
        if ($this->cartridge !== null) {
            $this->initializeSystem();
        }
    }

    /**
     * Run the main emulation loop.
     *
     * Runs continuously until stopped or an error occurs.
     * Automatically throttles to 59.7 FPS.
     */
    public function run(): void
    {
        if ($this->cpu === null) {
            throw new \RuntimeException("Cannot run: system not initialized");
        }

        $this->running = true;
        $frameTime = 1.0 / (self::TARGET_FPS * $this->speedMultiplier);
        $targetTime = microtime(true);
        $maxFrameSkip = 5; // Maximum frames to skip when catching up

        while ($this->running) {
            $currentTime = microtime(true);
            $framesBehind = (int)(($currentTime - $targetTime) / $frameTime);

            // Limit frame skip to prevent spiral of death
            if ($framesBehind > $maxFrameSkip) {
                // Reset timing if we're too far behind
                $targetTime = $currentTime;
                $framesBehind = 0;
            }

            // Execute one frame
            $this->step();

            // Advance target time for next frame
            $targetTime += $frameTime;

            // Frame timing/throttling
            $currentTime = microtime(true);
            $sleepTime = $targetTime - $currentTime;

            if ($sleepTime > 0) {
                usleep((int)($sleepTime * 1_000_000));
            }
        }
    }

    /**
     * Execute a single frame (70224 CPU cycles).
     *
     * Steps all subsystems until a complete frame has been rendered.
     */
    public function step(): void
    {
        if ($this->cpu === null || $this->ppu === null || $this->apu === null) {
            throw new \RuntimeException("Cannot step: system not initialized");
        }

        if ($this->paused) {
            return;
        }

        // Poll input and update joypad
        if ($this->input !== null && $this->joypad !== null) {
            $pressedButtons = $this->input->poll();
            $this->joypad->updateFromInput($pressedButtons);
        }

        $frameCycles = 0;

        while ($frameCycles < self::CYCLES_PER_FRAME) {
            // Execute one CPU instruction
            // With M-cycle accuracy, all components are advanced during
            // instruction execution via the cycle callback
            $cycles = $this->cpu->step();

            // Accumulate cycles
            $frameCycles += $cycles;
        }

        // Display the rendered frame
        $this->framebuffer->present();

        // Flush audio samples for this frame to maintain sync with video
        $this->audioSink->flush();
    }

    /**
     * Execute a single CPU instruction.
     *
     * With M-cycle accuracy, all components are automatically advanced
     * during instruction execution via the cycle callback.
     *
     * @return int Number of cycles executed
     */
    public function stepInstruction(): int
    {
        if ($this->cpu === null) {
            throw new \RuntimeException("Cannot step instruction: CPU not initialized");
        }

        // Execute CPU instruction
        // All components are automatically stepped via the cycle callback
        $cycles = $this->cpu->step();

        return $cycles;
    }

    /**
     * Reset the emulator to initial state.
     */
    public function reset(): void
    {
        if ($this->cartridge !== null) {
            $this->initializeSystem();
        }
    }

    /**
     * Stop the emulation loop.
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Pause emulation.
     */
    public function pause(): void
    {
        $this->paused = true;
    }

    /**
     * Resume emulation.
     */
    public function resume(): void
    {
        $this->paused = false;
    }

    /**
     * Check if emulator is running.
     */
    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Check if emulator is paused.
     */
    public function isPaused(): bool
    {
        return $this->paused;
    }

    /**
     * Get the CPU instance.
     */
    public function getCpu(): ?Cpu
    {
        return $this->cpu;
    }

    /**
     * Get the PPU instance.
     */
    public function getPpu(): ?Ppu
    {
        return $this->ppu;
    }

    /**
     * Get the system bus.
     */
    public function getBus(): ?SystemBus
    {
        return $this->bus;
    }

    /**
     * Get the framebuffer.
     */
    public function getFramebuffer(): FramebufferInterface
    {
        return $this->framebuffer;
    }

    /**
     * Get the input handler.
     */
    public function getInput(): ?InputInterface
    {
        return $this->input;
    }

    /**
     * Get the audio sink.
     */
    public function getAudioSink(): AudioSinkInterface
    {
        return $this->audioSink;
    }

    /**
     * Get the clock.
     */
    public function getClock(): Clock
    {
        return $this->clock;
    }

    /**
     * Get the loaded cartridge.
     */
    public function getCartridge(): ?Cartridge
    {
        return $this->cartridge;
    }

    /**
     * Get the serial device.
     */
    public function getSerial(): ?Serial
    {
        return $this->serial;
    }

    /**
     * Get the timer.
     */
    public function getTimer(): ?Timer
    {
        return $this->timer;
    }

    /**
     * Get the interrupt controller.
     */
    public function getInterruptController(): ?InterruptController
    {
        return $this->interruptController;
    }

    /**
     * Get the CGB controller.
     */
    public function getCgbController(): ?CgbController
    {
        return $this->cgb;
    }

    /**
     * Get the APU.
     */
    public function getApu(): ?Apu
    {
        return $this->apu;
    }

    /**
     * Get the OAM DMA controller.
     */
    public function getOamDma(): ?\Gb\Dma\OamDma
    {
        return $this->oamDma;
    }

    /**
     * Get the HDMA controller.
     */
    public function getHdma(): ?\Gb\Dma\HdmaController
    {
        return $this->hdma;
    }

    /**
     * Save the current emulator state to a file.
     *
     * @param string $path Path to save the savestate file
     * @throws \RuntimeException If savestate cannot be created or saved
     */
    public function saveState(string $path): void
    {
        $manager = new \Gb\Savestate\SavestateManager($this);
        $manager->save($path);
    }

    /**
     * Load an emulator state from a file.
     *
     * @param string $path Path to the savestate file
     * @throws \RuntimeException If savestate cannot be loaded or is invalid
     */
    public function loadState(string $path): void
    {
        $manager = new \Gb\Savestate\SavestateManager($this);
        $manager->load($path);
    }

    /**
     * Save a screenshot of the current framebuffer.
     *
     * @param string $path Path to save the screenshot (.ppm or .txt)
     * @param string $format Format: 'ppm', 'ppm-binary', or 'text' (default: 'ppm-binary')
     * @throws \RuntimeException If screenshot cannot be saved
     */
    public function screenshot(string $path, string $format = 'ppm-binary'): void
    {
        match($format) {
            'ppm' => \Gb\Support\Screenshot::savePPM($this->framebuffer, $path),
            'ppm-binary' => \Gb\Support\Screenshot::savePPMBinary($this->framebuffer, $path),
            'text' => \Gb\Support\Screenshot::saveText($this->framebuffer, $path),
            default => throw new \InvalidArgumentException("Invalid screenshot format: {$format}"),
        };
    }
}
