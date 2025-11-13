<?php

declare(strict_types=1);

namespace Gb\Savestate;

use Gb\Emulator;

/**
 * Manages savestate creation and restoration for the emulator.
 *
 * Savestates capture the complete state of the emulator at a specific point in time,
 * allowing users to save and restore their game progress instantly.
 *
 * Format: JSON (human-readable, debuggable)
 * Version: 1.0.0
 *
 * Savestate includes:
 * - CPU registers (AF, BC, DE, HL, SP, PC, IME, halted, stopped)
 * - Memory: VRAM, WRAM, HRAM, OAM, cartridge RAM
 * - PPU state: mode, cycle count, LY, scroll registers, palettes
 * - APU state: channel registers, frame sequencer position
 * - Timer state: DIV, TIMA, TMA, TAC
 * - Interrupt state: IF, IE
 * - Cartridge state: current ROM/RAM banks
 * - RTC state (if MBC3)
 * - Clock: total cycle count
 */
final class SavestateManager
{
    private const VERSION = '1.0.0';
    private const MAGIC = 'PHPBOY_SAVESTATE';

    private Emulator $emulator;

    public function __construct(Emulator $emulator)
    {
        $this->emulator = $emulator;
    }

    /**
     * Save the current emulator state to a file.
     *
     * @param string $path Path to save the savestate file
     * @throws \RuntimeException If savestate cannot be created or saved
     */
    public function save(string $path): void
    {
        $state = $this->serialize();
        $json = json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);

        if (file_put_contents($path, $json) === false) {
            throw new \RuntimeException("Failed to save savestate to: {$path}");
        }
    }

    /**
     * Load an emulator state from a file.
     *
     * @param string $path Path to the savestate file
     * @throws \RuntimeException If savestate cannot be loaded or is invalid
     */
    public function load(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Savestate file not found: {$path}");
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new \RuntimeException("Failed to read savestate file: {$path}");
        }

        try {
            $state = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid savestate format: " . $e->getMessage());
        }

        if (!is_array($state)) {
            throw new \RuntimeException("Invalid savestate format: expected array");
        }

        $this->validateState($state);
        $this->deserialize($state);
    }

    /**
     * Serialize the current emulator state to an array.
     *
     * @return array<string, mixed> Serialized emulator state
     */
    public function serialize(): array
    {
        $cpu = $this->emulator->getCpu();
        $ppu = $this->emulator->getPpu();
        $bus = $this->emulator->getBus();
        $cartridge = $this->emulator->getCartridge();
        $clock = $this->emulator->getClock();
        $timer = $this->emulator->getTimer();
        $interruptController = $this->emulator->getInterruptController();

        if ($cpu === null || $ppu === null || $bus === null || $cartridge === null) {
            throw new \RuntimeException("Cannot create savestate: emulator not initialized");
        }

        $cgbController = $this->emulator->getCgbController();
        $apu = $this->emulator->getApu();

        return [
            'magic' => self::MAGIC,
            'version' => self::VERSION,
            'timestamp' => time(),
            'cpu' => $this->serializeCpu($cpu),
            'ppu' => $this->serializePpu($ppu),
            'memory' => $this->serializeMemory($bus),
            'cartridge' => $this->serializeCartridge($cartridge),
            'timer' => $timer !== null ? $this->serializeTimer($timer) : null,
            'interrupts' => $interruptController !== null ? $this->serializeInterrupts($interruptController) : null,
            'cgb' => $cgbController !== null ? $this->serializeCgb($cgbController) : null,
            'apu' => $apu !== null ? $this->serializeApu($apu) : null,
            'clock' => [
                'cycles' => $clock->getCycles(),
            ],
        ];
    }

    /**
     * Deserialize and restore emulator state from an array.
     *
     * @param array<string, mixed> $state Serialized emulator state
     */
    public function deserialize(array $state): void
    {
        $cpu = $this->emulator->getCpu();
        $ppu = $this->emulator->getPpu();
        $bus = $this->emulator->getBus();
        $cartridge = $this->emulator->getCartridge();
        $clock = $this->emulator->getClock();
        $timer = $this->emulator->getTimer();
        $interruptController = $this->emulator->getInterruptController();

        if ($cpu === null || $ppu === null || $bus === null || $cartridge === null) {
            throw new \RuntimeException("Cannot load savestate: emulator not initialized");
        }

        if (!is_array($state['cpu'] ?? null)) {
            throw new \RuntimeException("Invalid savestate: cpu data missing or invalid");
        }
        if (!is_array($state['ppu'] ?? null)) {
            throw new \RuntimeException("Invalid savestate: ppu data missing or invalid");
        }
        if (!is_array($state['memory'] ?? null)) {
            throw new \RuntimeException("Invalid savestate: memory data missing or invalid");
        }
        if (!is_array($state['cartridge'] ?? null)) {
            throw new \RuntimeException("Invalid savestate: cartridge data missing or invalid");
        }

        $this->deserializeCpu($cpu, $state['cpu']);
        $this->deserializePpu($ppu, $state['ppu']);
        $this->deserializeMemory($bus, $state['memory']);
        $this->deserializeCartridge($cartridge, $state['cartridge']);

        // Restore timer state (optional for backward compatibility)
        if (isset($state['timer']) && is_array($state['timer']) && $timer !== null) {
            $this->deserializeTimer($timer, $state['timer']);
        }

        // Restore interrupt state (optional for backward compatibility)
        if (isset($state['interrupts']) && is_array($state['interrupts']) && $interruptController !== null) {
            $this->deserializeInterrupts($interruptController, $state['interrupts']);
        }

        // Restore CGB controller state (optional for backward compatibility)
        $cgbController = $this->emulator->getCgbController();
        if (isset($state['cgb']) && is_array($state['cgb']) && $cgbController !== null) {
            $this->deserializeCgb($cgbController, $state['cgb']);
        }

        // Restore APU state (optional for backward compatibility)
        $apu = $this->emulator->getApu();
        if (isset($state['apu']) && is_array($state['apu']) && $apu !== null) {
            $this->deserializeApu($apu, $state['apu']);
        }

        // Restore clock
        if (isset($state['clock']['cycles']) && is_int($state['clock']['cycles'])) {
            $clock->reset();
            $clock->tick($state['clock']['cycles']);
        }
    }

    /**
     * Serialize CPU state.
     *
     * @return array<string, mixed>
     */
    private function serializeCpu(\Gb\Cpu\Cpu $cpu): array
    {
        return [
            'af' => $cpu->getAF()->get(),
            'bc' => $cpu->getBC()->get(),
            'de' => $cpu->getDE()->get(),
            'hl' => $cpu->getHL()->get(),
            'sp' => $cpu->getSP()->get(),
            'pc' => $cpu->getPC()->get(),
            'ime' => $cpu->getIME(),
            'halted' => $cpu->isHalted(),
        ];
    }

    /**
     * Deserialize CPU state.
     *
     * @param array<string, mixed> $data
     */
    private function deserializeCpu(\Gb\Cpu\Cpu $cpu, array $data): void
    {
        $cpu->setAF((int) $data['af']);
        $cpu->setBC((int) $data['bc']);
        $cpu->setDE((int) $data['de']);
        $cpu->setHL((int) $data['hl']);
        $cpu->setSP((int) $data['sp']);
        $cpu->setPC((int) $data['pc']);
        $cpu->setIME((bool) $data['ime']);
        $cpu->setHalted((bool) $data['halted']);
    }

    /**
     * Serialize PPU state.
     *
     * @return array<string, mixed>
     */
    private function serializePpu(\Gb\Ppu\Ppu $ppu): array
    {
        $colorPalette = $ppu->getColorPalette();

        return [
            'mode' => $ppu->getMode()->value,
            'modeClock' => $ppu->getModeClock(),
            'ly' => $ppu->getLY(),
            'lyc' => $ppu->getLYC(),
            'scx' => $ppu->getSCX(),
            'scy' => $ppu->getSCY(),
            'wx' => $ppu->getWX(),
            'wy' => $ppu->getWY(),
            'lcdc' => $ppu->getLCDC(),
            'stat' => $ppu->getSTAT(),
            'bgp' => $ppu->getBGP(),
            'obp0' => $ppu->getOBP0(),
            'obp1' => $ppu->getOBP1(),
            'cgbPalette' => [
                'bgPalette' => base64_encode(pack('C*', ...$colorPalette->getBgPaletteMemory())),
                'objPalette' => base64_encode(pack('C*', ...$colorPalette->getObjPaletteMemory())),
                'bgIndex' => $colorPalette->getBgIndexRaw(),
                'objIndex' => $colorPalette->getObjIndexRaw(),
            ],
        ];
    }

    /**
     * Deserialize PPU state.
     *
     * @param array<string, mixed> $data
     */
    private function deserializePpu(\Gb\Ppu\Ppu $ppu, array $data): void
    {
        $ppu->restoreMode(\Gb\Ppu\PpuMode::from((int) $data['mode']));
        $ppu->setModeClock((int) $data['modeClock']);
        $ppu->setLY((int) $data['ly']);
        $ppu->setLYC((int) $data['lyc']);
        $ppu->setSCX((int) $data['scx']);
        $ppu->setSCY((int) $data['scy']);
        $ppu->setWX((int) $data['wx']);
        $ppu->setWY((int) $data['wy']);
        $ppu->setLCDC((int) $data['lcdc']);
        $ppu->setSTAT((int) $data['stat']);
        $ppu->setBGP((int) $data['bgp']);
        $ppu->setOBP0((int) $data['obp0']);
        $ppu->setOBP1((int) $data['obp1']);

        // Restore CGB color palettes (optional for backward compatibility)
        if (isset($data['cgbPalette']) && is_array($data['cgbPalette'])) {
            $colorPalette = $ppu->getColorPalette();

            if (isset($data['cgbPalette']['bgPalette'])) {
                $bgPaletteUnpacked = unpack('C*', base64_decode((string) $data['cgbPalette']['bgPalette']));
                if ($bgPaletteUnpacked !== false) {
                    $colorPalette->setBgPaletteMemory(array_values($bgPaletteUnpacked));
                }
            }

            if (isset($data['cgbPalette']['objPalette'])) {
                $objPaletteUnpacked = unpack('C*', base64_decode((string) $data['cgbPalette']['objPalette']));
                if ($objPaletteUnpacked !== false) {
                    $colorPalette->setObjPaletteMemory(array_values($objPaletteUnpacked));
                }
            }

            if (isset($data['cgbPalette']['bgIndex'])) {
                $colorPalette->setBgIndexRaw((int) $data['cgbPalette']['bgIndex']);
            }

            if (isset($data['cgbPalette']['objIndex'])) {
                $colorPalette->setObjIndexRaw((int) $data['cgbPalette']['objIndex']);
            }
        }
    }

    /**
     * Serialize memory state.
     *
     * @return array<string, mixed>
     */
    private function serializeMemory(\Gb\Bus\SystemBus $bus): array
    {
        $ppu = $this->emulator->getPpu();
        if ($ppu === null) {
            throw new \RuntimeException("Cannot serialize memory: PPU not initialized");
        }

        $vram = $ppu->getVram();

        // Save both VRAM banks (CGB has 2 banks, DMG only uses bank 0)
        $vramBank0 = $vram->getData(0);
        $vramBank1 = $vram->getData(1);
        $currentVramBank = $vram->getBank();

        $wram = [];
        for ($i = 0xC000; $i <= 0xDFFF; $i++) {
            $wram[] = $bus->readByte($i);
        }

        $hram = [];
        for ($i = 0xFF80; $i <= 0xFFFE; $i++) {
            $hram[] = $bus->readByte($i);
        }

        $oam = [];
        for ($i = 0xFE00; $i <= 0xFE9F; $i++) {
            $oam[] = $bus->readByte($i);
        }

        return [
            'vramBank0' => base64_encode(pack('C*', ...$vramBank0)),
            'vramBank1' => base64_encode(pack('C*', ...$vramBank1)),
            'vramCurrentBank' => $currentVramBank,
            'wram' => base64_encode(pack('C*', ...$wram)),
            'hram' => base64_encode(pack('C*', ...$hram)),
            'oam' => base64_encode(pack('C*', ...$oam)),
        ];
    }

    /**
     * Deserialize memory state.
     *
     * @param array<string, mixed> $data
     */
    private function deserializeMemory(\Gb\Bus\SystemBus $bus, array $data): void
    {
        $ppu = $this->emulator->getPpu();
        if ($ppu === null) {
            throw new \RuntimeException("Cannot deserialize memory: PPU not initialized");
        }

        $vram = $ppu->getVram();

        // Restore VRAM (support both old and new formats)
        if (isset($data['vramBank0']) && isset($data['vramBank1'])) {
            // New format: both banks saved separately
            $vramBank0Unpacked = unpack('C*', base64_decode((string) $data['vramBank0']));
            if ($vramBank0Unpacked === false) {
                throw new \RuntimeException('Failed to unpack VRAM bank 0 data');
            }
            $vramBank0Data = array_values($vramBank0Unpacked);

            $vramBank1Unpacked = unpack('C*', base64_decode((string) $data['vramBank1']));
            if ($vramBank1Unpacked === false) {
                throw new \RuntimeException('Failed to unpack VRAM bank 1 data');
            }
            $vramBank1Data = array_values($vramBank1Unpacked);

            // Restore to both banks by switching bank and writing
            $originalBank = $vram->getBank();

            $vram->setBank(0);
            for ($i = 0; $i < count($vramBank0Data); $i++) {
                $bus->writeByte(0x8000 + $i, $vramBank0Data[$i]);
            }

            $vram->setBank(1);
            for ($i = 0; $i < count($vramBank1Data); $i++) {
                $bus->writeByte(0x8000 + $i, $vramBank1Data[$i]);
            }

            // Restore original bank selection
            $currentBank = isset($data['vramCurrentBank']) ? (int) $data['vramCurrentBank'] : 0;
            $vram->setBank($currentBank);
        } elseif (isset($data['vram'])) {
            // Old format: only one bank (backward compatibility)
            $vramUnpacked = unpack('C*', base64_decode((string) $data['vram']));
            if ($vramUnpacked === false) {
                throw new \RuntimeException('Failed to unpack VRAM data');
            }
            $vramData = array_values($vramUnpacked);
            for ($i = 0; $i < count($vramData); $i++) {
                $bus->writeByte(0x8000 + $i, $vramData[$i]);
            }
        }

        // Restore WRAM
        $wramUnpacked = unpack('C*', base64_decode((string) $data['wram']));
        if ($wramUnpacked === false) {
            throw new \RuntimeException('Failed to unpack WRAM data');
        }
        $wram = array_values($wramUnpacked);
        for ($i = 0; $i < count($wram); $i++) {
            $bus->writeByte(0xC000 + $i, $wram[$i]);
        }

        // Restore HRAM
        $hramUnpacked = unpack('C*', base64_decode((string) $data['hram']));
        if ($hramUnpacked === false) {
            throw new \RuntimeException('Failed to unpack HRAM data');
        }
        $hram = array_values($hramUnpacked);
        for ($i = 0; $i < count($hram); $i++) {
            $bus->writeByte(0xFF80 + $i, $hram[$i]);
        }

        // Restore OAM
        $oamUnpacked = unpack('C*', base64_decode((string) $data['oam']));
        if ($oamUnpacked === false) {
            throw new \RuntimeException('Failed to unpack OAM data');
        }
        $oam = array_values($oamUnpacked);
        for ($i = 0; $i < count($oam); $i++) {
            $bus->writeByte(0xFE00 + $i, $oam[$i]);
        }
    }

    /**
     * Serialize cartridge state.
     *
     * @return array<string, mixed>
     */
    private function serializeCartridge(\Gb\Cartridge\Cartridge $cartridge): array
    {
        return [
            'romBank' => $cartridge->getCurrentRomBank(),
            'ramBank' => $cartridge->getCurrentRamBank(),
            'ramEnabled' => $cartridge->isRamEnabled(),
            'ram' => base64_encode($cartridge->getRamData()),
        ];
    }

    /**
     * Deserialize cartridge state.
     *
     * @param array<string, mixed> $data
     */
    private function deserializeCartridge(\Gb\Cartridge\Cartridge $cartridge, array $data): void
    {
        $cartridge->setCurrentRomBank((int) $data['romBank']);
        $cartridge->setCurrentRamBank((int) $data['ramBank']);
        $cartridge->setRamEnabled((bool) $data['ramEnabled']);
        $cartridge->loadRamData((string) $data['ram']);
    }

    /**
     * Serialize Timer state.
     *
     * @return array<string, mixed>
     */
    private function serializeTimer(\Gb\Timer\Timer $timer): array
    {
        return [
            'div' => $timer->getDiv(),
            'divCounter' => $timer->getDivCounter(),
            'tima' => $timer->getTima(),
            'tma' => $timer->getTma(),
            'tac' => $timer->getTac(),
            'timaCounter' => $timer->getTimaCounter(),
        ];
    }

    /**
     * Deserialize Timer state.
     *
     * @param array<string, mixed> $data
     */
    private function deserializeTimer(\Gb\Timer\Timer $timer, array $data): void
    {
        $timer->setDiv((int) ($data['div'] ?? 0));
        $timer->setDivCounter((int) ($data['divCounter'] ?? 0));
        $timer->setTima((int) ($data['tima'] ?? 0));
        $timer->setTma((int) ($data['tma'] ?? 0));
        $timer->setTac((int) ($data['tac'] ?? 0));
        $timer->setTimaCounter((int) ($data['timaCounter'] ?? 0));
    }

    /**
     * Serialize Interrupt state.
     *
     * @return array<string, mixed>
     */
    private function serializeInterrupts(\Gb\Interrupts\InterruptController $interrupts): array
    {
        return [
            'if' => $interrupts->readByte(0xFF0F),
            'ie' => $interrupts->readByte(0xFFFF),
        ];
    }

    /**
     * Deserialize Interrupt state.
     *
     * @param array<string, mixed> $data
     */
    private function deserializeInterrupts(\Gb\Interrupts\InterruptController $interrupts, array $data): void
    {
        $interrupts->writeByte(0xFF0F, (int) ($data['if'] ?? 0xE0));
        $interrupts->writeByte(0xFFFF, (int) ($data['ie'] ?? 0x00));
    }

    /**
     * Serialize CGB controller state.
     *
     * @return array<string, mixed>
     */
    private function serializeCgb(\Gb\System\CgbController $cgb): array
    {
        return [
            'key0' => $cgb->getKey0(),
            'key1' => $cgb->getKey1(),
            'opri' => $cgb->getOpri(),
            'doubleSpeed' => $cgb->isDoubleSpeed(),
            'key0Writable' => $cgb->isKey0Writable(),
        ];
    }

    /**
     * Deserialize CGB controller state.
     *
     * @param array<string, mixed> $data
     */
    private function deserializeCgb(\Gb\System\CgbController $cgb, array $data): void
    {
        $cgb->setKey0((int) ($data['key0'] ?? 0));
        $cgb->setKey1((int) ($data['key1'] ?? 0));
        $cgb->setOpri((int) ($data['opri'] ?? 0));
        $cgb->setDoubleSpeed((bool) ($data['doubleSpeed'] ?? false));
        $cgb->setKey0Writable((bool) ($data['key0Writable'] ?? true));
    }

    /**
     * Serialize APU state.
     *
     * Note: This saves register state and basic APU state, but not full channel
     * internal state (timers, counters). This provides partial audio restoration.
     *
     * @return array<string, mixed>
     */
    private function serializeApu(\Gb\Apu\Apu $apu): array
    {
        // Save all APU registers by reading them
        $registers = [
            // Channel 1
            'nr10' => $apu->readByte(0xFF10),
            'nr11' => $apu->readByte(0xFF11),
            'nr12' => $apu->readByte(0xFF12),
            'nr13' => $apu->readByte(0xFF13),
            'nr14' => $apu->readByte(0xFF14),

            // Channel 2
            'nr21' => $apu->readByte(0xFF16),
            'nr22' => $apu->readByte(0xFF17),
            'nr23' => $apu->readByte(0xFF18),
            'nr24' => $apu->readByte(0xFF19),

            // Channel 3
            'nr30' => $apu->readByte(0xFF1A),
            'nr31' => $apu->readByte(0xFF1B),
            'nr32' => $apu->readByte(0xFF1C),
            'nr33' => $apu->readByte(0xFF1D),
            'nr34' => $apu->readByte(0xFF1E),

            // Channel 4
            'nr41' => $apu->readByte(0xFF20),
            'nr42' => $apu->readByte(0xFF21),
            'nr43' => $apu->readByte(0xFF22),
            'nr44' => $apu->readByte(0xFF23),

            // Master control
            'nr50' => $apu->readByte(0xFF24),
            'nr51' => $apu->readByte(0xFF25),
            'nr52' => $apu->readByte(0xFF26),
        ];

        return [
            'registers' => $registers,
            'waveRam' => base64_encode(pack('C*', ...$apu->getWaveRam())),
            'frameSequencerCycles' => $apu->getFrameSequencerCycles(),
            'frameSequencerStep' => $apu->getFrameSequencerStep(),
            'sampleCycles' => $apu->getSampleCycles(),
            'enabled' => $apu->isEnabled(),
        ];
    }

    /**
     * Deserialize APU state.
     *
     * @param array<string, mixed> $data
     */
    private function deserializeApu(\Gb\Apu\Apu $apu, array $data): void
    {
        // Restore APU registers
        if (isset($data['registers']) && is_array($data['registers'])) {
            $reg = $data['registers'];

            // Channel 1
            $apu->writeByte(0xFF10, (int) ($reg['nr10'] ?? 0));
            $apu->writeByte(0xFF11, (int) ($reg['nr11'] ?? 0));
            $apu->writeByte(0xFF12, (int) ($reg['nr12'] ?? 0));
            $apu->writeByte(0xFF13, (int) ($reg['nr13'] ?? 0));
            $apu->writeByte(0xFF14, (int) ($reg['nr14'] ?? 0));

            // Channel 2
            $apu->writeByte(0xFF16, (int) ($reg['nr21'] ?? 0));
            $apu->writeByte(0xFF17, (int) ($reg['nr22'] ?? 0));
            $apu->writeByte(0xFF18, (int) ($reg['nr23'] ?? 0));
            $apu->writeByte(0xFF19, (int) ($reg['nr24'] ?? 0));

            // Channel 3
            $apu->writeByte(0xFF1A, (int) ($reg['nr30'] ?? 0));
            $apu->writeByte(0xFF1B, (int) ($reg['nr31'] ?? 0));
            $apu->writeByte(0xFF1C, (int) ($reg['nr32'] ?? 0));
            $apu->writeByte(0xFF1D, (int) ($reg['nr33'] ?? 0));
            $apu->writeByte(0xFF1E, (int) ($reg['nr34'] ?? 0));

            // Channel 4
            $apu->writeByte(0xFF20, (int) ($reg['nr41'] ?? 0));
            $apu->writeByte(0xFF21, (int) ($reg['nr42'] ?? 0));
            $apu->writeByte(0xFF22, (int) ($reg['nr43'] ?? 0));
            $apu->writeByte(0xFF23, (int) ($reg['nr44'] ?? 0));

            // Master control
            $apu->writeByte(0xFF24, (int) ($reg['nr50'] ?? 0));
            $apu->writeByte(0xFF25, (int) ($reg['nr51'] ?? 0));
            $apu->writeByte(0xFF26, (int) ($reg['nr52'] ?? 0));
        }

        // Restore Wave RAM
        if (isset($data['waveRam'])) {
            $waveRamUnpacked = unpack('C*', base64_decode((string) $data['waveRam']));
            if ($waveRamUnpacked !== false) {
                $apu->setWaveRam(array_values($waveRamUnpacked));
            }
        }

        // Restore internal state
        if (isset($data['frameSequencerCycles'])) {
            $apu->setFrameSequencerCycles((int) $data['frameSequencerCycles']);
        }
        if (isset($data['frameSequencerStep'])) {
            $apu->setFrameSequencerStep((int) $data['frameSequencerStep']);
        }
        if (isset($data['sampleCycles'])) {
            $apu->setSampleCycles((float) $data['sampleCycles']);
        }
        if (isset($data['enabled'])) {
            $apu->setEnabled((bool) $data['enabled']);
        }
    }

    /**
     * Validate savestate format and version.
     *
     * @param array<string, mixed> $state
     * @throws \RuntimeException If savestate is invalid
     */
    private function validateState(array $state): void
    {
        if (!isset($state['magic']) || $state['magic'] !== self::MAGIC) {
            throw new \RuntimeException("Invalid savestate: magic number mismatch");
        }

        if (!isset($state['version'])) {
            throw new \RuntimeException("Invalid savestate: missing version");
        }

        // Version compatibility check
        // For now, we only support exact version match
        if ($state['version'] !== self::VERSION) {
            throw new \RuntimeException(
                "Incompatible savestate version: " . (string) $state['version'] . " (expected " . self::VERSION . ")"
            );
        }

        // Validate required fields
        $required = ['cpu', 'ppu', 'memory', 'cartridge', 'clock'];
        foreach ($required as $field) {
            if (!isset($state[$field])) {
                throw new \RuntimeException("Invalid savestate: missing '{$field}' field");
            }
        }
    }
}
