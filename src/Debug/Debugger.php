<?php

declare(strict_types=1);

namespace Gb\Debug;

use Gb\Emulator;

/**
 * Interactive Debugger for PHPBoy
 *
 * Provides GDB-style debugging interface with:
 * - Breakpoints
 * - Single-step execution
 * - Register and memory inspection
 * - Disassembly
 *
 * Commands:
 * - step / s: Execute one instruction
 * - continue / c: Run until breakpoint
 * - break <addr> / b <addr>: Set breakpoint
 * - delete <n>: Remove breakpoint
 * - registers / r: Display CPU registers
 * - memory <addr> / m <addr>: Display memory
 * - disassemble <addr> / d <addr>: Disassemble instructions
 * - stack: Display stack contents
 * - frame: Display PPU state
 * - reset: Reset emulator
 * - quit / q: Exit debugger
 */
final class Debugger
{
    /** @var array<int, bool> Breakpoints (address => enabled) */
    private array $breakpoints = [];

    private int $nextBreakpointId = 1;

    /** @var array<int, int> Breakpoint ID to address mapping */
    private array $breakpointAddresses = [];

    private ?Disassembler $disassembler = null;

    public function __construct(
        private readonly Emulator $emulator
    ) {
        $bus = $this->emulator->getBus();
        if ($bus !== null) {
            $this->disassembler = new Disassembler($bus);
        }
    }

    /**
     * Run the interactive debugger shell.
     */
    public function run(): void
    {
        echo "PHPBoy Debugger\n";
        echo "===============\n\n";
        echo "Type 'help' for command list\n\n";

        $this->showCurrentInstruction();

        while (true) {
            $input = $this->prompt();

            if ($input === false || $input === '') {
                continue;
            }

            $parts = preg_split('/\s+/', trim($input));
            if ($parts === false || count($parts) === 0) {
                continue;
            }

            $command = strtolower($parts[0]);
            $args = array_slice($parts, 1);

            $shouldQuit = $this->executeCommand($command, $args);
            if ($shouldQuit) {
                break;
            }
        }

        echo "\nDebugger exited.\n";
    }

    /**
     * Prompt for user input.
     *
     * @return string|false User input or false on EOF
     */
    private function prompt(): string|false
    {
        echo "(phpboy-dbg) ";
        $input = fgets(STDIN);
        return $input !== false ? rtrim($input, "\r\n") : false;
    }

    /**
     * Execute a debugger command.
     *
     * @param string $command Command name
     * @param array<int, string> $args Command arguments
     * @return bool True if should quit debugger
     */
    private function executeCommand(string $command, array $args): bool
    {
        return match ($command) {
            'help', 'h', '?' => $this->cmdHelp(),
            'step', 's' => $this->cmdStep(),
            'continue', 'c' => $this->cmdContinue(),
            'break', 'b' => $this->cmdBreak($args),
            'delete', 'del', 'd' => $this->cmdDelete($args),
            'breakpoints', 'info', 'i' => $this->cmdBreakpoints(),
            'registers', 'r', 'reg' => $this->cmdRegisters(),
            'memory', 'm', 'mem' => $this->cmdMemory($args),
            'disassemble', 'disasm', 'da' => $this->cmdDisassemble($args),
            'stack', 'st' => $this->cmdStack(),
            'frame', 'f' => $this->cmdFrame(),
            'reset' => $this->cmdReset(),
            'savestate', 'save' => $this->cmdSavestate($args),
            'loadstate', 'load' => $this->cmdLoadstate($args),
            'screenshot', 'ss' => $this->cmdScreenshot($args),
            'speed' => $this->cmdSpeed($args),
            'quit', 'q', 'exit' => true,
            default => $this->cmdUnknown($command),
        };
    }

    /**
     * Show help message.
     */
    private function cmdHelp(): bool
    {
        echo <<<HELP
Debugger Commands:
  step, s                 Execute one instruction
  continue, c             Run until breakpoint
  break <addr>, b <addr>  Set breakpoint at address (hex)
  delete <n>, d <n>       Delete breakpoint by ID
  breakpoints, i          List all breakpoints
  registers, r            Display CPU registers
  memory <addr>, m <addr> Display memory at address (hex)
  disassemble [addr]      Disassemble instructions
  stack                   Display stack contents
  frame                   Display PPU state
  reset                   Reset emulator
  savestate <path>        Save emulator state to file
  loadstate <path>        Load emulator state from file
  screenshot <path>       Take screenshot to PPM file
  speed <factor>          Set emulation speed (1.0 = normal)
  quit, q                 Exit debugger
  help, h                 Show this help

Examples:
  b 0x100             Set breakpoint at 0x0100
  m 0xC000            Show memory at 0xC000
  s                   Execute one instruction
  c                   Continue until breakpoint
  savestate save.state  Save current state
  loadstate save.state  Load saved state
  screenshot frame.ppm  Take screenshot
  speed 2.0           Run at 2x speed


HELP;
        return false;
    }

    /**
     * Step one instruction.
     */
    private function cmdStep(): bool
    {
        $this->emulator->stepInstruction();
        $this->showCurrentInstruction();
        return false;
    }

    /**
     * Continue execution until breakpoint.
     */
    private function cmdContinue(): bool
    {
        echo "Continuing...\n";

        $cpu = $this->emulator->getCpu();
        if ($cpu === null) {
            echo "Error: CPU not initialized\n";
            return false;
        }

        $maxInstructions = 1000000; // Prevent infinite loops
        $instructionCount = 0;

        while ($instructionCount < $maxInstructions) {
            $pc = $cpu->getPC()->get();

            if (isset($this->breakpoints[$pc]) && $this->breakpoints[$pc]) {
                echo sprintf("Breakpoint hit at 0x%04X\n", $pc);
                $this->showCurrentInstruction();
                return false;
            }

            $this->emulator->stepInstruction();
            $instructionCount++;
        }

        echo "Warning: Execution limit reached\n";
        $this->showCurrentInstruction();
        return false;
    }

    /**
     * Set a breakpoint.
     *
     * @param array<int, string> $args Arguments
     */
    private function cmdBreak(array $args): bool
    {
        if (count($args) === 0) {
            echo "Usage: break <address>\n";
            echo "Example: break 0x100\n";
            return false;
        }

        $address = $this->parseAddress($args[0]);
        if ($address === null) {
            echo "Invalid address: {$args[0]}\n";
            return false;
        }

        $id = $this->nextBreakpointId++;
        $this->breakpoints[$address] = true;
        $this->breakpointAddresses[$id] = $address;

        echo sprintf("Breakpoint %d set at 0x%04X\n", $id, $address);
        return false;
    }

    /**
     * Delete a breakpoint.
     *
     * @param array<int, string> $args Arguments
     */
    private function cmdDelete(array $args): bool
    {
        if (count($args) === 0) {
            echo "Usage: delete <breakpoint-id>\n";
            return false;
        }

        $id = (int)$args[0];

        if (!isset($this->breakpointAddresses[$id])) {
            echo "No breakpoint with ID {$id}\n";
            return false;
        }

        $address = $this->breakpointAddresses[$id];
        unset($this->breakpoints[$address]);
        unset($this->breakpointAddresses[$id]);

        echo sprintf("Breakpoint %d (0x%04X) deleted\n", $id, $address);
        return false;
    }

    /**
     * List all breakpoints.
     */
    private function cmdBreakpoints(): bool
    {
        if (count($this->breakpointAddresses) === 0) {
            echo "No breakpoints set\n";
            return false;
        }

        echo "Breakpoints:\n";
        foreach ($this->breakpointAddresses as $id => $address) {
            $enabled = $this->breakpoints[$address] ? 'enabled' : 'disabled';
            echo sprintf("  %d: 0x%04X (%s)\n", $id, $address, $enabled);
        }

        return false;
    }

    /**
     * Display CPU registers.
     */
    private function cmdRegisters(): bool
    {
        $cpu = $this->emulator->getCpu();
        if ($cpu === null) {
            echo "Error: CPU not initialized\n";
            return false;
        }

        $flags = $cpu->getFlags();

        echo "CPU Registers:\n";
        echo sprintf("  AF: 0x%04X  (A: 0x%02X, F: %s%s%s%s)\n",
            $cpu->getAF()->get(),
            $cpu->getA(),
            $flags->getZ() ? 'Z' : '-',
            $flags->getN() ? 'N' : '-',
            $flags->getH() ? 'H' : '-',
            $flags->getC() ? 'C' : '-'
        );
        echo sprintf("  BC: 0x%04X  (B: 0x%02X, C: 0x%02X)\n", $cpu->getBC()->get(), $cpu->getB(), $cpu->getC());
        echo sprintf("  DE: 0x%04X  (D: 0x%02X, E: 0x%02X)\n", $cpu->getDE()->get(), $cpu->getD(), $cpu->getE());
        echo sprintf("  HL: 0x%04X  (H: 0x%02X, L: 0x%02X)\n", $cpu->getHL()->get(), $cpu->getH(), $cpu->getL());
        echo sprintf("  SP: 0x%04X\n", $cpu->getSP()->get());
        echo sprintf("  PC: 0x%04X\n", $cpu->getPC()->get());

        return false;
    }

    /**
     * Display memory.
     *
     * @param array<int, string> $args Arguments
     */
    private function cmdMemory(array $args): bool
    {
        $bus = $this->emulator->getBus();
        if ($bus === null) {
            echo "Error: Bus not initialized\n";
            return false;
        }

        $address = count($args) > 0 ? $this->parseAddress($args[0]) : null;
        $cpu = $this->emulator->getCpu();

        if ($address === null) {
            $address = $cpu?->getPC()->get() ?? 0;
        }

        echo sprintf("Memory at 0x%04X:\n", $address);

        // Display 16 rows of 16 bytes
        for ($row = 0; $row < 16; $row++) {
            $rowAddress = ($address + ($row * 16)) & 0xFFFF;
            echo sprintf("%04X: ", $rowAddress);

            // Hex bytes
            $bytes = [];
            for ($col = 0; $col < 16; $col++) {
                $byte = $bus->readByte(($rowAddress + $col) & 0xFFFF);
                $bytes[] = $byte;
                echo sprintf("%02X ", $byte);
            }

            // ASCII representation
            echo " | ";
            foreach ($bytes as $byte) {
                $char = ($byte >= 0x20 && $byte <= 0x7E) ? chr($byte) : '.';
                echo $char;
            }
            echo "\n";
        }

        return false;
    }

    /**
     * Disassemble instructions.
     *
     * @param array<int, string> $args Arguments
     */
    private function cmdDisassemble(array $args): bool
    {
        if ($this->disassembler === null) {
            echo "Error: Disassembler not available\n";
            return false;
        }

        $cpu = $this->emulator->getCpu();
        $address = count($args) > 0 ? $this->parseAddress($args[0]) : null;

        if ($address === null) {
            $address = $cpu?->getPC()->get() ?? 0;
        }

        $instructions = $this->disassembler->disassemble($address, 20);

        foreach ($instructions as $instr) {
            echo Disassembler::format($instr) . "\n";
        }

        return false;
    }

    /**
     * Display stack.
     */
    private function cmdStack(): bool
    {
        $cpu = $this->emulator->getCpu();
        $bus = $this->emulator->getBus();

        if ($cpu === null || $bus === null) {
            echo "Error: System not initialized\n";
            return false;
        }

        $sp = $cpu->getSP()->get();
        echo sprintf("Stack (SP=0x%04X):\n", $sp);

        // Show 16 words on the stack
        for ($i = 0; $i < 16; $i++) {
            $address = ($sp + ($i * 2)) & 0xFFFF;
            $low = $bus->readByte($address);
            $high = $bus->readByte(($address + 1) & 0xFFFF);
            $word = $low | ($high << 8);
            echo sprintf("  %04X: %04X\n", $address, $word);
        }

        return false;
    }

    /**
     * Display PPU frame state.
     */
    private function cmdFrame(): bool
    {
        $ppu = $this->emulator->getPpu();
        $bus = $this->emulator->getBus();

        if ($ppu === null || $bus === null) {
            echo "Error: PPU/Bus not initialized\n";
            return false;
        }

        // Read PPU registers directly from bus
        $lcdc = $bus->readByte(0xFF40);
        $stat = $bus->readByte(0xFF41);
        $ly = $bus->readByte(0xFF44);
        $lyc = $bus->readByte(0xFF45);
        $scy = $bus->readByte(0xFF42);
        $scx = $bus->readByte(0xFF43);

        echo "PPU State:\n";
        echo sprintf("  LCDC: 0x%02X (LCD %s)\n", $lcdc, ($lcdc & 0x80) ? 'ON' : 'OFF');
        echo sprintf("  STAT: 0x%02X (Mode %d)\n", $stat, $stat & 0x03);
        echo sprintf("  LY:   %d\n", $ly);
        echo sprintf("  LYC:  %d\n", $lyc);
        echo sprintf("  SCY:  %d\n", $scy);
        echo sprintf("  SCX:  %d\n", $scx);

        return false;
    }

    /**
     * Reset emulator.
     */
    private function cmdReset(): bool
    {
        echo "Resetting emulator...\n";
        $this->emulator->reset();
        $this->showCurrentInstruction();
        return false;
    }

    /**
     * Save emulator state to file.
     *
     * @param array<int, string> $args
     */
    private function cmdSavestate(array $args): bool
    {
        if (count($args) === 0) {
            echo "Usage: savestate <path>\n";
            echo "Example: savestate debug.state\n";
            return false;
        }

        $path = $args[0];

        try {
            $this->emulator->saveState($path);
            echo "Saved state to {$path}\n";
        } catch (\Exception $e) {
            echo "Error saving state: {$e->getMessage()}\n";
        }

        return false;
    }

    /**
     * Load emulator state from file.
     *
     * @param array<int, string> $args
     */
    private function cmdLoadstate(array $args): bool
    {
        if (count($args) === 0) {
            echo "Usage: loadstate <path>\n";
            echo "Example: loadstate debug.state\n";
            return false;
        }

        $path = $args[0];

        try {
            $this->emulator->loadState($path);
            echo "Loaded state from {$path}\n";
            $this->showCurrentInstruction();
        } catch (\Exception $e) {
            echo "Error loading state: {$e->getMessage()}\n";
        }

        return false;
    }

    /**
     * Take a screenshot.
     *
     * @param array<int, string> $args
     */
    private function cmdScreenshot(array $args): bool
    {
        if (count($args) === 0) {
            echo "Usage: screenshot <path>\n";
            echo "Example: screenshot frame.ppm\n";
            return false;
        }

        $path = $args[0];

        try {
            // Default to binary PPM format
            $this->emulator->screenshot($path, 'ppm-binary');
            echo "Screenshot saved to {$path}\n";
        } catch (\Exception $e) {
            echo "Error saving screenshot: {$e->getMessage()}\n";
        }

        return false;
    }

    /**
     * Set emulation speed multiplier.
     *
     * @param array<int, string> $args
     */
    private function cmdSpeed(array $args): bool
    {
        if (count($args) === 0) {
            echo "Usage: speed <factor>\n";
            echo "Example: speed 2.0 (2x speed)\n";
            echo "         speed 0.5 (half speed)\n";
            echo "         speed 1.0 (normal speed)\n";
            return false;
        }

        $speed = (float)$args[0];

        if ($speed <= 0) {
            echo "Error: Speed must be positive\n";
            return false;
        }

        $this->emulator->setSpeed($speed);
        echo sprintf("Speed set to %.1fx\n", $speed);

        return false;
    }

    /**
     * Unknown command.
     */
    private function cmdUnknown(string $command): bool
    {
        echo "Unknown command: {$command}\n";
        echo "Type 'help' for command list\n";
        return false;
    }

    /**
     * Show the current instruction.
     */
    private function showCurrentInstruction(): void
    {
        if ($this->disassembler === null) {
            return;
        }

        $cpu = $this->emulator->getCpu();
        if ($cpu === null) {
            return;
        }

        $pc = $cpu->getPC()->get();
        $instruction = $this->disassembler->disassembleOne($pc);

        echo "=> " . Disassembler::format($instruction) . "\n";
    }

    /**
     * Parse address from string (supports hex with 0x prefix).
     *
     * @param string $str Address string
     * @return int|null Parsed address or null if invalid
     */
    private function parseAddress(string $str): ?int
    {
        $str = strtolower(trim($str));

        if (str_starts_with($str, '0x')) {
            $hex = substr($str, 2);
            $value = hexdec($hex);
            return $value !== 0 || $hex === '0' || $hex === '00' ? (int)$value : null;
        }

        if (ctype_digit($str)) {
            return (int)$str;
        }

        // Try parsing as hex without 0x prefix
        if (ctype_xdigit($str)) {
            return (int)hexdec($str);
        }

        return null;
    }
}
