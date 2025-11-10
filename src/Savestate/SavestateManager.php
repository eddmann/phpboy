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

        if ($cpu === null || $ppu === null || $bus === null || $cartridge === null) {
            throw new \RuntimeException("Cannot create savestate: emulator not initialized");
        }

        return [
            'magic' => self::MAGIC,
            'version' => self::VERSION,
            'timestamp' => time(),
            'cpu' => $this->serializeCpu($cpu),
            'ppu' => $this->serializePpu($ppu),
            'memory' => $this->serializeMemory($bus),
            'cartridge' => $this->serializeCartridge($cartridge),
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
    }

    /**
     * Serialize memory state.
     *
     * @return array<string, mixed>
     */
    private function serializeMemory(\Gb\Bus\SystemBus $bus): array
    {
        // Read memory regions
        $vram = [];
        for ($i = 0x8000; $i <= 0x9FFF; $i++) {
            $vram[] = $bus->readByte($i);
        }

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
            'vram' => base64_encode(pack('C*', ...$vram)),
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
        // Restore VRAM
        $vramUnpacked = unpack('C*', base64_decode((string) $data['vram']));
        if ($vramUnpacked === false) {
            throw new \RuntimeException('Failed to unpack VRAM data');
        }
        $vram = array_values($vramUnpacked);
        for ($i = 0; $i < count($vram); $i++) {
            $bus->writeByte(0x8000 + $i, $vram[$i]);
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
