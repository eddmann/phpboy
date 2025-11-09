<?php

declare(strict_types=1);

namespace Gb\Cpu;

use Gb\Bus\BusInterface;
use Gb\Cpu\Register\FlagRegister;
use Gb\Cpu\Register\Register16;
use Gb\Interrupts\InterruptController;

/**
 * LR35902 CPU (Sharp SM83)
 *
 * The Game Boy CPU is a hybrid of the Intel 8080 and Zilog Z80.
 * It features 8-bit registers (A, B, C, D, E, H, L, F) that can be paired
 * into 16-bit registers (AF, BC, DE, HL), plus 16-bit stack pointer (SP)
 * and program counter (PC).
 *
 * CPU operates at 4.194304 MHz (DMG) or 8.388608 MHz (CGB double-speed mode).
 *
 * Reference: Pan Docs - CPU Registers and Instruction Set
 */
final class Cpu
{
    // 16-bit register pairs
    private Register16 $af;
    private Register16 $bc;
    private Register16 $de;
    private Register16 $hl;
    private Register16 $sp;
    private Register16 $pc;

    // Flag register (lower byte of AF)
    private FlagRegister $flags;

    // CPU state
    private bool $halted = false;
    private bool $stopped = false;
    private bool $ime = false; // Interrupt Master Enable
    private int $imeDelay = 0; // EI instruction has 1-instruction delay

    // M-cycle tracking (1 M-cycle = 4 T-cycles)
    private int $pendingCycles = 0;
    private ?\Closure $cycleCallback = null;

    /**
     * @param BusInterface $bus Memory bus for reading/writing memory
     * @param InterruptController $interruptController Interrupt controller
     */
    public function __construct(
        private readonly BusInterface $bus,
        private readonly InterruptController $interruptController,
    ) {
        // Initialize registers to their power-up state
        // After boot ROM execution, PC should be at 0x0100
        $this->af = new Register16(0x0000);
        $this->bc = new Register16(0x0000);
        $this->de = new Register16(0x0000);
        $this->hl = new Register16(0x0000);
        $this->sp = new Register16(0xFFFE); // Stack grows downward from 0xFFFE
        $this->pc = new Register16(0x0100); // Start of cartridge ROM

        // Link flags register to AF register for automatic synchronization
        $this->flags = new FlagRegister(0x00, $this->af);
    }

    /**
     * Execute one instruction and return the number of cycles consumed.
     *
     * Handles interrupts, HALT, and the EI delay.
     * With M-cycle accuracy, cycles are advanced during execution via callbacks,
     * but we still return the total for compatibility.
     *
     * @return int Number of CPU cycles consumed (typically 4, 8, 12, 16, 20, or 24)
     */
    public function step(): int
    {
        // Track total cycles for return value
        $cyclesStart = $this->pendingCycles;

        // Handle EI delay (IME is enabled after the instruction following EI)
        if ($this->imeDelay > 0) {
            $this->imeDelay--;
            if ($this->imeDelay === 0) {
                $this->ime = true;
            }
        }

        // Check for interrupts before fetching
        if ($this->ime) {
            $interrupt = $this->interruptController->getPendingInterrupt();
            if ($interrupt !== null) {
                $this->halted = false; // Wake from HALT
                $this->serviceInterrupt($interrupt);
                $this->flushPendingCycles();
                $totalCycles = 20; // Interrupt service always takes 20 T-cycles
                return $totalCycles;
            }
        }

        // If halted, check if we should wake up
        if ($this->halted) {
            // HALT wakes on any pending interrupt, even if IME=0
            if ($this->interruptController->hasAnyPendingInterrupt()) {
                $this->halted = false;
                // HALT bug: if IME=0 and interrupt pending, PC doesn't increment
                // For now, we'll implement the simple behavior
            } else {
                // Still halted, consume 4 cycles
                $this->advanceCycles(4);
                return 4;
            }
        }

        // Optimization (Step 14): Inline instruction decode and execute to eliminate method call overhead
        // Expected: 3-7% performance gain by removing decode() and execute() method calls
        $opcode = $this->fetch();
        $instruction = InstructionSet::getInstruction($opcode);
        $cycles = ($instruction->handler)($this);

        // Flush any remaining pending cycles
        $this->flushPendingCycles();

        return $cycles;
    }

    /**
     * Service an interrupt by pushing PC to stack and jumping to the vector.
     *
     * Timing breakdown (5 M-cycles = 20 T-cycles):
     * - 2 M-cycles: Internal delay
     * - 1 M-cycle: Write PC high byte to stack
     * - 1 M-cycle: Write PC low byte to stack
     * - 1 M-cycle: Jump to interrupt vector
     *
     * @param \Gb\Interrupts\InterruptType $interrupt The interrupt to service
     */
    private function serviceInterrupt(\Gb\Interrupts\InterruptType $interrupt): void
    {
        // Disable IME
        $this->ime = false;
        $this->imeDelay = 0;

        // Acknowledge the interrupt
        $this->interruptController->acknowledgeInterrupt($interrupt);

        // Internal delay: 2 M-cycles
        $this->cycleNoAccess();
        $this->cycleNoAccess();

        // Push PC to stack: 2 M-cycles (1 per write)
        $pc = $this->pc->get();
        $this->sp->decrement();
        $this->cycleWrite($this->sp->get(), ($pc >> 8) & 0xFF); // High byte
        $this->sp->decrement();
        $this->cycleWrite($this->sp->get(), $pc & 0xFF); // Low byte

        // Jump to interrupt vector: 1 M-cycle
        $this->pc->set($interrupt->getVector());
        $this->cycleNoAccess();
    }

    /**
     * Fetch the next opcode byte from memory at PC and increment PC.
     *
     * Takes 1 M-cycle (4 T-cycles) to read from memory.
     *
     * @return int The opcode byte (0x00-0xFF)
     */
    public function fetch(): int
    {
        $opcode = $this->cycleRead($this->pc->get());
        $this->pc->increment();
        return $opcode;
    }

    /**
     * Decode an opcode into an Instruction.
     *
     * @param int $opcode The opcode byte
     * @return Instruction The decoded instruction
     */
    public function decode(int $opcode): Instruction
    {
        return InstructionSet::getInstruction($opcode);
    }

    /**
     * Execute an instruction and return the number of cycles consumed.
     *
     * @param Instruction $instruction The instruction to execute
     * @return int Number of CPU cycles consumed
     */
    public function execute(Instruction $instruction): int
    {
        return ($instruction->handler)($this);
    }

    // Register accessors

    public function getAF(): Register16
    {
        return $this->af;
    }

    public function getBC(): Register16
    {
        return $this->bc;
    }

    public function getDE(): Register16
    {
        return $this->de;
    }

    public function getHL(): Register16
    {
        return $this->hl;
    }

    public function getSP(): Register16
    {
        return $this->sp;
    }

    public function getPC(): Register16
    {
        return $this->pc;
    }

    public function getFlags(): FlagRegister
    {
        return $this->flags;
    }

    /**
     * Get the A register (high byte of AF).
     *
     * @return int Value of A register (0x00-0xFF)
     */
    public function getA(): int
    {
        return $this->af->getHigh();
    }

    /**
     * Set the A register (high byte of AF).
     *
     * @param int $value New value for A register (0x00-0xFF)
     */
    public function setA(int $value): void
    {
        $this->af->setHigh($value);
    }

    /**
     * Get the F register (low byte of AF) - flags.
     *
     * @return int Value of F register (0x00-0xFF)
     */
    public function getF(): int
    {
        return $this->flags->get();
    }

    /**
     * Set the F register (low byte of AF) - flags.
     *
     * @param int $value New value for F register (0x00-0xFF)
     */
    public function setF(int $value): void
    {
        $this->flags->set($value);
    }

    /**
     * Get the B register (high byte of BC).
     *
     * @return int Value of B register (0x00-0xFF)
     */
    public function getB(): int
    {
        return $this->bc->getHigh();
    }

    /**
     * Set the B register (high byte of BC).
     *
     * @param int $value New value for B register (0x00-0xFF)
     */
    public function setB(int $value): void
    {
        $this->bc->setHigh($value);
    }

    /**
     * Get the C register (low byte of BC).
     *
     * @return int Value of C register (0x00-0xFF)
     */
    public function getC(): int
    {
        return $this->bc->getLow();
    }

    /**
     * Set the C register (low byte of BC).
     *
     * @param int $value New value for C register (0x00-0xFF)
     */
    public function setC(int $value): void
    {
        $this->bc->setLow($value);
    }

    /**
     * Get the D register (high byte of DE).
     *
     * @return int Value of D register (0x00-0xFF)
     */
    public function getD(): int
    {
        return $this->de->getHigh();
    }

    /**
     * Set the D register (high byte of DE).
     *
     * @param int $value New value for D register (0x00-0xFF)
     */
    public function setD(int $value): void
    {
        $this->de->setHigh($value);
    }

    /**
     * Get the E register (low byte of DE).
     *
     * @return int Value of E register (0x00-0xFF)
     */
    public function getE(): int
    {
        return $this->de->getLow();
    }

    /**
     * Set the E register (low byte of DE).
     *
     * @param int $value New value for E register (0x00-0xFF)
     */
    public function setE(int $value): void
    {
        $this->de->setLow($value);
    }

    /**
     * Get the H register (high byte of HL).
     *
     * @return int Value of H register (0x00-0xFF)
     */
    public function getH(): int
    {
        return $this->hl->getHigh();
    }

    /**
     * Set the H register (high byte of HL).
     *
     * @param int $value New value for H register (0x00-0xFF)
     */
    public function setH(int $value): void
    {
        $this->hl->setHigh($value);
    }

    /**
     * Get the L register (low byte of HL).
     *
     * @return int Value of L register (0x00-0xFF)
     */
    public function getL(): int
    {
        return $this->hl->getLow();
    }

    /**
     * Set the L register (low byte of HL).
     *
     * @param int $value New value for L register (0x00-0xFF)
     */
    public function setL(int $value): void
    {
        $this->hl->setLow($value);
    }

    /**
     * Get the memory bus.
     *
     * @return BusInterface The memory bus
     */
    public function getBus(): BusInterface
    {
        return $this->bus;
    }

    /**
     * Check if CPU is halted.
     *
     * @return bool True if halted
     */
    public function isHalted(): bool
    {
        return $this->halted;
    }

    /**
     * Set the halted state.
     *
     * @param bool $halted True to halt, false to resume
     */
    public function setHalted(bool $halted): void
    {
        $this->halted = $halted;
    }

    /**
     * Check if CPU is stopped.
     *
     * @return bool True if stopped
     */
    public function isStopped(): bool
    {
        return $this->stopped;
    }

    /**
     * Set the stopped state.
     *
     * @param bool $stopped True to stop, false to resume
     */
    public function setStopped(bool $stopped): void
    {
        $this->stopped = $stopped;
    }

    /**
     * Get the Interrupt Master Enable flag.
     *
     * @return bool True if interrupts are enabled
     */
    public function getIME(): bool
    {
        return $this->ime;
    }

    /**
     * Set the Interrupt Master Enable flag.
     *
     * For EI instruction: sets a delay so IME is enabled after the next instruction.
     * For DI instruction: disables IME immediately.
     *
     * @param bool $ime True to enable interrupts (with delay), false to disable immediately
     */
    public function setIME(bool $ime): void
    {
        if ($ime) {
            // EI: Enable after next instruction (1-instruction delay)
            $this->imeDelay = 1;
        } else {
            // DI: Disable immediately
            $this->ime = false;
            $this->imeDelay = 0;
        }
    }

    /**
     * Enable interrupts immediately without delay.
     *
     * Used by the RETI instruction, which enables IME immediately
     * (unlike EI which has a 1-instruction delay).
     */
    public function setIMEImmediate(): void
    {
        $this->ime = true;
        $this->imeDelay = 0;
    }

    /**
     * Get the interrupt controller.
     *
     * @return InterruptController The interrupt controller
     */
    public function getInterruptController(): InterruptController
    {
        return $this->interruptController;
    }

    /**
     * Set the cycle callback for M-cycle accurate emulation.
     *
     * The callback is invoked whenever cycles are advanced, allowing other
     * components (PPU, APU, Timer, etc.) to update in real-time during
     * instruction execution.
     *
     * @param \Closure $callback Function that takes int $cycles as parameter
     */
    public function setCycleCallback(\Closure $callback): void
    {
        $this->cycleCallback = $callback;
    }

    /**
     * Advance CPU cycles and invoke the cycle callback.
     *
     * This is the core of M-cycle accurate emulation - cycles are advanced
     * incrementally during instruction execution, not just at the end.
     *
     * @param int $cycles Number of T-cycles to advance (typically 4 per M-cycle)
     */
    private function advanceCycles(int $cycles): void
    {
        if ($this->cycleCallback !== null) {
            ($this->cycleCallback)($cycles);
        }
    }

    /**
     * Flush any pending cycles.
     *
     * Called before branches, interrupts, or other control flow changes.
     */
    private function flushPendingCycles(): void
    {
        if ($this->pendingCycles > 0) {
            $this->advanceCycles($this->pendingCycles);
            $this->pendingCycles = 0;
        }
    }

    /**
     * Read a byte from memory with M-cycle accurate timing.
     *
     * This function:
     * 1. Flushes any pending cycles
     * 2. Reads the byte from memory
     * 3. Sets pending cycles to 4 (1 M-cycle)
     *
     * @param int $address Memory address to read from
     * @return int The byte value read
     */
    public function cycleRead(int $address): int
    {
        $this->flushPendingCycles();
        $value = $this->bus->readByte($address);
        $this->pendingCycles = 4;
        return $value;
    }

    /**
     * Write a byte to memory with M-cycle accurate timing.
     *
     * This function:
     * 1. Flushes any pending cycles
     * 2. Writes the byte to memory
     * 3. Sets pending cycles to 4 (1 M-cycle)
     *
     * @param int $address Memory address to write to
     * @param int $value Byte value to write
     */
    public function cycleWrite(int $address, int $value): void
    {
        $this->flushPendingCycles();
        $this->bus->writeByte($address, $value);
        $this->pendingCycles = 4;
    }

    /**
     * Internal operation with no memory access (1 M-cycle).
     *
     * Used for ALU operations, internal register transfers, etc.
     * Accumulates cycles without flushing.
     */
    public function cycleNoAccess(): void
    {
        $this->pendingCycles += 4;
    }

    /**
     * Get pending cycles count.
     *
     * @return int Number of pending T-cycles
     */
    public function getPendingCycles(): int
    {
        return $this->pendingCycles;
    }

    /**
     * Set AF register pair value.
     *
     * @param int $value 16-bit value for AF
     */
    public function setAF(int $value): void
    {
        $this->af->set($value);
    }

    /**
     * Set BC register pair value.
     *
     * @param int $value 16-bit value for BC
     */
    public function setBC(int $value): void
    {
        $this->bc->set($value);
    }

    /**
     * Set DE register pair value.
     *
     * @param int $value 16-bit value for DE
     */
    public function setDE(int $value): void
    {
        $this->de->set($value);
    }

    /**
     * Set HL register pair value.
     *
     * @param int $value 16-bit value for HL
     */
    public function setHL(int $value): void
    {
        $this->hl->set($value);
    }

    /**
     * Set SP register value.
     *
     * @param int $value 16-bit value for SP
     */
    public function setSP(int $value): void
    {
        $this->sp->set($value);
    }

    /**
     * Set PC register value.
     *
     * @param int $value 16-bit value for PC
     */
    public function setPC(int $value): void
    {
        $this->pc->set($value);
    }
}
