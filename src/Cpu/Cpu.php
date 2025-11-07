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

        $this->flags = new FlagRegister();
    }

    /**
     * Execute one instruction and return the number of cycles consumed.
     *
     * Handles interrupts, HALT, and the EI delay.
     *
     * @return int Number of CPU cycles consumed (typically 4, 8, 12, 16, 20, or 24)
     */
    public function step(): int
    {
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
                return $this->serviceInterrupt($interrupt);
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
                return 4;
            }
        }

        $opcode = $this->fetch();
        $instruction = $this->decode($opcode);
        return $this->execute($instruction);
    }

    /**
     * Service an interrupt by pushing PC to stack and jumping to the vector.
     *
     * @param \Gb\Interrupts\InterruptType $interrupt The interrupt to service
     * @return int Number of CPU cycles consumed (20)
     */
    private function serviceInterrupt(\Gb\Interrupts\InterruptType $interrupt): int
    {
        // Disable IME
        $this->ime = false;
        $this->imeDelay = 0;

        // Acknowledge the interrupt
        $this->interruptController->acknowledgeInterrupt($interrupt);

        // Push PC to stack (takes 2 M-cycles = 8 T-cycles)
        $pc = $this->pc->get();
        $this->sp->decrement();
        $this->bus->writeByte($this->sp->get(), ($pc >> 8) & 0xFF); // High byte
        $this->sp->decrement();
        $this->bus->writeByte($this->sp->get(), $pc & 0xFF); // Low byte

        // Jump to interrupt vector (takes 1 M-cycle = 4 T-cycles)
        $this->pc->set($interrupt->getVector());

        // Total: 5 M-cycles = 20 T-cycles
        return 20;
    }

    /**
     * Fetch the next opcode byte from memory at PC and increment PC.
     *
     * @return int The opcode byte (0x00-0xFF)
     */
    public function fetch(): int
    {
        $opcode = $this->bus->readByte($this->pc->get());
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
     * Get the interrupt controller.
     *
     * @return InterruptController The interrupt controller
     */
    public function getInterruptController(): InterruptController
    {
        return $this->interruptController;
    }
}
