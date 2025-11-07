<?php

declare(strict_types=1);

namespace Gb\Debug;

use Gb\Emulator;

/**
 * CPU Instruction Tracer
 *
 * Logs each CPU instruction execution with register states and cycle counts.
 * Useful for debugging and comparing against other emulators or test ROMs.
 *
 * Output format:
 * [PC:0x0100] LD A,0x42 | AF:0042 BC:0000 DE:0000 HL:0000 SP:FFFE | Cycles: 8
 */
final class Trace
{
    private bool $enabled = false;
    private ?Disassembler $disassembler = null;

    /** @var resource|null Output file handle */
    private $outputHandle = null;

    private bool $outputToStdout = true;

    public function __construct(
        private readonly Emulator $emulator
    ) {
        $bus = $this->emulator->getBus();
        if ($bus !== null) {
            $this->disassembler = new Disassembler($bus);
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * Enable tracing.
     */
    public function enable(): void
    {
        $this->enabled = true;
    }

    /**
     * Disable tracing.
     */
    public function disable(): void
    {
        $this->enabled = false;
    }

    /**
     * Check if tracing is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Set output to a file.
     *
     * @param string $filename Output file path
     */
    public function setOutputFile(string $filename): void
    {
        $this->close();

        $handle = fopen($filename, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Failed to open trace file: {$filename}");
        }

        $this->outputHandle = $handle;
        $this->outputToStdout = false;
    }

    /**
     * Set output to stdout.
     */
    public function setOutputStdout(): void
    {
        $this->close();
        $this->outputToStdout = true;
    }

    /**
     * Trace the current instruction.
     *
     * Should be called before each CPU step.
     */
    public function trace(): void
    {
        if (!$this->enabled) {
            return;
        }

        $cpu = $this->emulator->getCpu();
        $bus = $this->emulator->getBus();

        if ($cpu === null || $bus === null || $this->disassembler === null) {
            return;
        }

        // Get current PC
        $pc = $cpu->getPC()->get();

        // Disassemble current instruction
        $instruction = $this->disassembler->disassembleOne($pc);

        // Get register states
        $af = $cpu->getAF()->get();
        $bc = $cpu->getBC()->get();
        $de = $cpu->getDE()->get();
        $hl = $cpu->getHL()->get();
        $sp = $cpu->getSP()->get();

        // Get flags
        $flagReg = $cpu->getFlags();
        $flags = sprintf(
            '%s%s%s%s',
            $flagReg->getZ() ? 'Z' : '-',
            $flagReg->getN() ? 'N' : '-',
            $flagReg->getH() ? 'H' : '-',
            $flagReg->getC() ? 'C' : '-'
        );

        // Format output
        $mnemonic = $instruction['mnemonic'];
        $operands = $instruction['operands'];
        $instr = $operands !== '' ? "{$mnemonic} {$operands}" : $mnemonic;

        $output = sprintf(
            "[PC:%04X] %-20s | AF:%04X BC:%04X DE:%04X HL:%04X SP:%04X | %s\n",
            $pc,
            $instr,
            $af,
            $bc,
            $de,
            $hl,
            $sp,
            $flags
        );

        $this->write($output);
    }

    /**
     * Write output to the configured destination.
     *
     * @param string $data Data to write
     */
    private function write(string $data): void
    {
        if ($this->outputToStdout) {
            echo $data;
        } elseif ($this->outputHandle !== null) {
            fwrite($this->outputHandle, $data);
        }
    }

    /**
     * Close the output file if open.
     */
    private function close(): void
    {
        if ($this->outputHandle !== null) {
            fclose($this->outputHandle);
            $this->outputHandle = null;
        }
    }
}
