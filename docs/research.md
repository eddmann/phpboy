# PHPBoy - Game Boy Color Emulator Research

## Executive Summary

This document provides a comprehensive technical reference for PHPBoy, a Game Boy Color (GBC) emulator written in PHP 8.5. The Game Boy line represents one of the most successful handheld gaming platforms in history, with the original Game Boy (DMG) launching in 1989 and the Game Boy Color arriving in 1998. Understanding the hardware architecture, timing characteristics, and design decisions behind these systems is essential for creating an accurate emulator.

The Game Boy's custom 8-bit Sharp LR35902 CPU, tile-based graphics system, 4-channel audio synthesizer, and sophisticated memory banking schemes all combine to create a platform that was both powerful for its time and surprisingly elegant in design. This research document synthesizes information from Pan Docs, hardware research notes, and emulator accuracy reports to provide a foundational knowledge base for PHPBoy development.

## 1. LR35902 CPU Architecture

### 1.1 Historical Context

The Sharp LR35902 CPU at the heart of the Game Boy is a fascinating hybrid design. It combines the instruction set of the Zilog Z80 with the register architecture more reminiscent of the Intel 8080. This choice was deliberate: the Z80's instruction set was well-understood by developers (having powered systems like the Sega Master System), while the 8080-style registers helped reduce silicon complexity and power consumption—critical factors for a battery-powered handheld.

The CPU runs at 4.194304 MHz in normal mode (DMG/CGB), with the Game Boy Color adding a double-speed mode at 8.388608 MHz. This clock speed, while modest by modern standards, was carefully chosen to synchronize with the PPU's dot clock and generate proper video timing.

### 1.2 Register File

The LR35902 features **six 16-bit registers** that can be accessed as pairs or individually as 8-bit registers:

- **AF**: Accumulator (A) and Flags (F) register
- **BC**: General-purpose register pair (B and C individually)
- **DE**: General-purpose register pair (D and E individually)
- **HL**: General-purpose register pair (H and L individually), often used for memory addressing
- **SP**: Stack Pointer (16-bit only)
- **PC**: Program Counter (16-bit only)

Most arithmetic and logical operations work with the A register (accumulator), while BC, DE, and HL serve as general-purpose storage or memory pointers. The HL register pair is particularly important, as many instructions use it for indirect memory access via the (HL) addressing mode.

### 1.3 Flags Register

The lower 8 bits of the AF register pair constitute the Flags register (F), containing four condition flags in the upper nibble:

| Bit | Flag | Name | Purpose |
|-----|------|------|---------|
| 7 | Z | Zero | Set when an operation result equals zero |
| 6 | N | Subtraction | Set for subtraction operations (used by DAA) |
| 5 | H | Half-Carry | Set when carry occurs from bit 3 to bit 4 |
| 4 | C | Carry | Set when carry occurs from bit 7 or borrow is needed |
| 3-0 | — | — | Always zero (cannot be set) |

**Flag Behavior Details:**

- **Zero Flag (Z)**: Set if and only if the result of an operation is 0x00. This applies to arithmetic, logical, and comparison operations.
- **Subtraction Flag (N)**: Used exclusively by the DAA (Decimal Adjust Accumulator) instruction to determine whether to add or subtract correction values for BCD arithmetic.
- **Half-Carry Flag (H)**: Critical for BCD operations and useful for debugging. Set when a carry occurs from bit 3 to bit 4 in 8-bit operations, or from bit 11 to bit 12 in 16-bit operations.
- **Carry Flag (C)**: Set when an 8-bit addition exceeds 0xFF, a 16-bit addition exceeds 0xFFFF, or when a subtraction requires borrowing.

### 1.4 Instruction Set

The LR35902 instruction set consists of 512 possible opcodes organized into two tables:

- **Primary opcodes** (0x00-0xFF): 256 main instructions including loads, arithmetic, logic, jumps, and calls
- **CB-prefixed opcodes** (0xCB00-0xCBFF): 256 bit manipulation instructions including rotates, shifts, and bit test/set/reset operations

Key instruction categories include:

- **8-bit loads**: LD r, r | LD r, n | LD r, (HL) | LD (HL), r | LD A, (BC)/(DE)/(nn)
- **16-bit loads**: LD rr, nn | PUSH rr | POP rr | LD SP, HL | LD (nn), SP
- **8-bit arithmetic**: ADD | ADC | SUB | SBC | AND | OR | XOR | CP | INC | DEC
- **16-bit arithmetic**: ADD HL, rr | INC rr | DEC rr | ADD SP, e
- **Rotates and shifts**: RLCA | RLA | RRCA | RRA | RLC | RL | RRC | RR | SLA | SRA | SRL
- **Bit operations**: BIT b, r | SET b, r | RES b, r | SWAP r
- **Jumps**: JP nn | JP cc, nn | JR e | JR cc, e
- **Calls and returns**: CALL nn | CALL cc, nn | RET | RET cc | RETI | RST n
- **Special**: NOP | HALT | STOP | DI | EI | DAA | CPL | CCF | SCF

### 1.5 Timing Characteristics

Every instruction on the LR35902 takes a multiple of 4 T-cycles (also called T-states) to execute. Most documentation refers to M-cycles (machine cycles), where 1 M-cycle = 4 T-cycles. Common instruction timings:

- **Simple operations**: 4 T-cycles (1 M-cycle) — e.g., NOP, register-to-register loads
- **Memory operations**: 8 T-cycles (2 M-cycles) — e.g., LD A, (HL)
- **16-bit operations**: 8-12 T-cycles — e.g., INC rr, ADD HL, rr
- **Jumps and calls**: 12-24 T-cycles — e.g., JP nn (16), CALL nn (24)
- **CB-prefixed**: 8-16 T-cycles depending on operation

Accurate cycle counting is essential for emulation, as games rely on precise timing for synchronization with PPU, APU, and timer interrupts.

### 1.6 Differences from Z80

While similar to the Z80, the LR35902 has several key differences:

- No IX, IY, or shadow registers
- No IN/OUT instructions (I/O is memory-mapped)
- Different set of illegal/undocumented opcodes
- Slightly different flag behavior in some instructions
- No interrupt modes (only one vectored interrupt system)

## 2. Memory Map

### 2.1 Complete Address Space ($0000-$FFFF)

The Game Boy uses a 16-bit address bus, providing 64KB of addressable space. This space is divided into several regions, some mapped to cartridge hardware, some to internal RAM, and some to memory-mapped I/O registers.

| Address Range | Size | Region | Description |
|---------------|------|--------|-------------|
| $0000-$00FF | 256 bytes | ROM Bank 0 | Interrupt vectors and boot ROM area |
| $0100-$014F | 80 bytes | ROM Bank 0 | Cartridge header (title, checksums, MBC type) |
| $0150-$3FFF | ~16 KB | ROM Bank 0 | Fixed cartridge ROM bank (always bank 0) |
| $4000-$7FFF | 16 KB | ROM Bank N | Switchable cartridge ROM bank (1-N via MBC) |
| $8000-$97FF | 6 KB | VRAM | Tile data (banks 0-1 on CGB) |
| $9800-$9BFF | 1 KB | VRAM | Background tile map 0 |
| $9C00-$9FFF | 1 KB | VRAM | Background tile map 1 |
| $A000-$BFFF | 8 KB | External RAM | Cartridge RAM (if present, switchable via MBC) |
| $C000-$CFFF | 4 KB | WRAM Bank 0 | Work RAM (always bank 0) |
| $D000-$DFFF | 4 KB | WRAM Bank N | Work RAM (banks 1-7 on CGB) |
| $E000-$FDFF | ~7.5 KB | Echo RAM | Mirror of C000-DDFF (prohibited, avoid using) |
| $FE00-$FE9F | 160 bytes | OAM | Object Attribute Memory (sprite data) |
| $FEA0-$FEFF | 96 bytes | Prohibited | Not usable, behavior varies |
| $FF00-$FF7F | 128 bytes | I/O Registers | Hardware control registers |
| $FF80-$FFFE | 127 bytes | HRAM | High RAM (fast access, usable during DMA) |
| $FFFF | 1 byte | IE | Interrupt Enable register |

### 2.2 Special Memory Regions

**Interrupt Vectors ($0000-$003F)**

The first 64 bytes of memory contain interrupt handlers:
- $0040: V-Blank interrupt
- $0048: LCD STAT interrupt
- $0050: Timer overflow interrupt
- $0058: Serial transfer complete interrupt
- $0060: Joypad press interrupt

**Cartridge Header ($0100-$014F)**

Essential metadata including:
- $0100-$0103: Entry point (usually a jump instruction)
- $0104-$0133: Nintendo logo (must match expected pattern)
- $0134-$0143: Game title (up to 15 characters)
- $0143: CGB compatibility flag
- $0147: Cartridge type (indicates MBC type)
- $0148: ROM size
- $0149: RAM size
- $014D: Header checksum
- $014E-$014F: Global checksum

**Echo RAM ($E000-$FDFF)**

This region mirrors $C000-$DDFF. All reads and writes to echo RAM have the same effect as accessing the corresponding WRAM address. Nintendo documentation prohibits using this area, and some hardware revisions may behave differently.

**Prohibited Area ($FEA0-$FEFF)**

This 96-byte region has undefined behavior. Some hardware revisions return the last value on the bus, others return 0xFF, and some return values from OAM. Emulators typically return 0xFF or mirror nearby memory.

### 2.3 I/O Registers ($FF00-$FF7F)

Critical hardware control registers include:

- **$FF00 (JOYP)**: Joypad input
- **$FF01-$FF02**: Serial transfer
- **$FF04-$FF07**: Timer and divider
- **$FF0F (IF)**: Interrupt flags
- **$FF10-$FF3F**: Audio (APU) registers
- **$FF40-$FF4B**: LCD control and status (PPU)
- **$FF4D**: CGB speed switch
- **$FF4F**: VRAM bank select (CGB)
- **$FF50**: Boot ROM disable
- **$FF51-$FF55**: VRAM DMA (CGB)
- **$FF68-$FF6B**: Palette data (CGB)
- **$FF70**: WRAM bank select (CGB)

### 2.4 CGB Enhancements

The Game Boy Color extended the memory system with:
- **VRAM banking**: 8KB × 2 banks (bank 1 stores tile attributes)
- **WRAM banking**: 4KB fixed + 4KB × 7 switchable banks
- **Color palettes**: 64 bytes for background + 64 bytes for sprites

## 3. Picture Processing Unit (PPU)

### 3.1 Overview and Timing

The Game Boy's PPU is a tile-based graphics system capable of rendering a 160×144 pixel display. It operates in lockstep with the CPU, consuming a fixed number of dots (pixel clocks) per scanline.

**Frame Structure:**
- **Total scanlines per frame**: 154
- **Visible scanlines**: 144 (0-143)
- **V-Blank scanlines**: 10 (144-153)
- **Dots per scanline**: 456
- **Total dots per frame**: 70,224
- **Frame rate**: ~59.7 Hz (4,194,304 Hz ÷ 70,224 dots)

### 3.2 PPU Modes

The PPU cycles through four modes during each scanline:

| Mode | Name | Duration | Description |
|------|------|----------|-------------|
| 2 | OAM Scan | 80 dots | Searches for sprites on current scanline (up to 10) |
| 3 | Drawing | 172-289 dots | Renders pixels and sends them to LCD |
| 0 | H-Blank | 87-204 dots | Waits until scanline completes (total 456 dots) |
| 1 | V-Blank | 4560 dots | Waits for next frame (10 scanlines × 456 dots) |

**Mode 3 Variable Timing:**

Mode 3 duration varies based on several factors:
- **Background scroll**: SCX % 8 adds initial penalty
- **Window activation**: 6-dot penalty when window becomes visible
- **Sprites**: 6-11 dot penalty per sprite overlapping the scanline (depends on alignment)

These penalties are observable on real hardware and affect pixel timing.

### 3.3 VRAM Layout

**Tile Data ($8000-$97FF)**

Tiles are 8×8 pixel graphics stored as 16 bytes (2 bytes per row):
- Each pixel uses 2 bits (4 colors)
- Byte 0: low bit of color, Byte 1: high bit of color
- Tiles can be addressed in two modes:
  - **Unsigned mode** ($8000-$8FFF): Tiles 0-255
  - **Signed mode** ($8800-$97FF): Tiles -128 to 127 (offset from $9000)

**Tile Maps ($9800-$9FFF)**

Two 32×32 tile maps (each 1024 bytes) define which tiles appear where:
- **Map 0**: $9800-$9BFF
- **Map 1**: $9C00-$9FFF

Each byte is a tile index. On CGB, VRAM bank 1 contains tile attributes (palette, flip, priority).

**OAM ($FE00-$FE9F)**

Object Attribute Memory stores data for up to 40 sprites (4 bytes each):
- Byte 0: Y position (minus 16)
- Byte 1: X position (minus 8)
- Byte 2: Tile number
- Byte 3: Attributes (palette, flip, priority, bank)

### 3.4 Rendering Pipeline

1. **Mode 2 (OAM Scan)**: PPU scans all 40 sprites, selecting up to 10 whose Y position overlaps the current scanline
2. **Mode 3 (Drawing)**:
   - Fetch background tile index from tile map
   - Fetch tile data from tile memory
   - Mix with window if enabled
   - Overlay sprites (max 10 per scanline)
   - Apply palette transformations
   - Output pixel to screen buffer
3. **Mode 0 (H-Blank)**: Idle until scanline completes
4. **Mode 1 (V-Blank)**: After scanline 143, PPU enters V-Blank for 10 scanlines; V-Blank interrupt triggers

### 3.5 LCD Control Register (LCDC, $FF40)

| Bit | Name | Description |
|-----|------|-------------|
| 7 | LCD Enable | 0=Off, 1=On (turning off mid-frame can damage hardware!) |
| 6 | Window Tile Map | 0=$9800-$9BFF, 1=$9C00-$9FFF |
| 5 | Window Enable | 0=Off, 1=On |
| 4 | BG/Window Tile Data | 0=$8800-$97FF (signed), 1=$8000-$8FFF (unsigned) |
| 3 | BG Tile Map | 0=$9800-$9BFF, 1=$9C00-$9FFF |
| 2 | OBJ Size | 0=8×8, 1=8×16 |
| 1 | OBJ Enable | 0=Off, 1=On |
| 0 | BG/Window Enable | 0=Off, 1=On (on CGB, affects priority instead) |

### 3.6 Color Game Boy Enhancements

CGB adds:
- **VRAM Bank 1**: Tile attributes (palette select, flip, priority, bank)
- **Color palettes**: 8 background × 4 colors, 8 sprite × 4 colors
- **15-bit color**: 5 bits per channel (RGB555 format)
- **Palette registers**: BCPS/BCPD for background, OCPS/OCPD for sprites

## 4. Audio Processing Unit (APU)

### 4.1 Overview

The Game Boy APU synthesizes audio using **four independent channels**, each with distinct characteristics. The APU operates independently of the CPU, generating samples through a combination of hardware oscillators, envelope generators, and noise generators.

**Master Control:**
- **NR52 ($FF26)**: Power control and channel status
  - Bit 7: Power APU on/off
  - Bits 3-0: Read-only status (which channels are active)
- **NR51 ($FF25)**: Panning (L/R output per channel)
- **NR50 ($FF24)**: Master volume (0-7 scale)

### 4.2 Channel 1: Square Wave with Sweep

**Registers:**
- **NR10 ($FF10)**: Sweep control (period, direction, shift)
- **NR11 ($FF11)**: Length timer and duty cycle
- **NR12 ($FF12)**: Volume envelope (initial volume, direction, period)
- **NR13 ($FF13)**: Frequency low byte
- **NR14 ($FF14)**: Trigger, length enable, frequency high bits

**Features:**
- **Sweep**: Automatically adjusts frequency over time (upward or downward)
- **Duty cycles**: 12.5%, 25%, 50%, 75% (controls waveform shape)
- **Volume envelope**: Increases or decreases volume over time

Typical use: Melody lines, lead instruments

### 4.3 Channel 2: Square Wave

**Registers:**
- **NR21 ($FF16)**: Length timer and duty cycle
- **NR22 ($FF17)**: Volume envelope
- **NR23 ($FF18)**: Frequency low byte
- **NR24 ($FF19)**: Trigger, length enable, frequency high bits

Identical to Channel 1 but without sweep capability. Commonly used for harmony or countermelody.

### 4.4 Channel 3: Programmable Wave

**Registers:**
- **NR30 ($FF1A)**: DAC enable
- **NR31 ($FF1B)**: Length timer
- **NR32 ($FF1C)**: Output level (0%, 25%, 50%, 100%)
- **NR33 ($FF1D)**: Frequency low byte
- **NR34 ($FF1E)**: Trigger, length enable, frequency high bits
- **Wave RAM ($FF30-$FF3F)**: 16 bytes (32 4-bit samples)

**Features:**
- **Custom waveforms**: 32 samples at 4 bits each
- **Arbitrary sounds**: Can approximate sine waves, sawtooth, triangle, etc.
- **Bass instrument**: Often used for bass lines or unique sound effects

### 4.5 Channel 4: Noise

**Registers:**
- **NR41 ($FF20)**: Length timer
- **NR42 ($FF21)**: Volume envelope
- **NR43 ($FF22)**: Frequency and randomness (clock shift, LFSR width, divisor)
- **NR44 ($FF23)**: Trigger, length enable

**Features:**
- **LFSR (Linear Feedback Shift Register)**: Generates pseudo-random bit patterns
- **15-bit and 7-bit modes**: 7-bit mode produces more metallic/tonal noise
- **Frequency control**: Clock divider and shift amount determine pitch

Typical use: Percussion (drums, hi-hats), explosions, white noise effects

### 4.6 Frame Sequencer

The APU uses a **frame sequencer** running at 512 Hz to clock length counters, envelopes, and sweep:

| Step | Actions |
|------|---------|
| 0 | Clock length counter |
| 1 | — |
| 2 | Clock length counter and sweep |
| 3 | — |
| 4 | Clock length counter |
| 5 | — |
| 6 | Clock length counter and sweep |
| 7 | Clock volume envelope |

This 8-step pattern repeats continuously, providing precise timing for audio effects.

## 5. Memory Bank Controllers (MBCs)

### 5.1 Purpose and Function

The Game Boy's 16-bit address bus limits direct ROM access to 32KB (two 16KB banks). To create larger games, Nintendo developed **Memory Bank Controllers (MBCs)**—specialized chips on the cartridge that allow bank switching, extending addressable ROM to several megabytes and adding external RAM.

### 5.2 MBC1 (Max 2MB ROM, 32KB RAM)

**Bank Switching Behavior:**
- **0x0000-0x1FFF**: RAM enable (write 0x0A to enable)
- **0x2000-0x3FFF**: Select ROM bank (5-bit, banks 0x01-0x1F)
- **0x4000-0x5FFF**: Select upper ROM bits or RAM bank (2-bit)
- **0x6000-0x7FFF**: Banking mode (0=ROM mode, 1=RAM mode)

**Special Cases:**
- Writing 0x00 to ROM bank select automatically changes to 0x01
- Banks 0x20, 0x40, 0x60 also redirect to 0x21, 0x41, 0x61

**Banking Modes:**
- **ROM mode**: All 7 bits select ROM bank (up to 125 banks)
- **RAM mode**: 2 upper bits select RAM bank or upper ROM bits

### 5.3 MBC3 (Max 2MB ROM, 32KB RAM, RTC)

**Bank Switching Behavior:**
- **0x0000-0x1FFF**: RAM and timer enable
- **0x2000-0x3FFF**: Select ROM bank (7-bit, banks 0x01-0x7F)
- **0x4000-0x5FFF**: Select RAM bank (0x00-0x03) or RTC register (0x08-0x0C)
- **0x6000-0x7FFF**: Latch clock data (write 0x00 then 0x01)

**Real-Time Clock (RTC):**
- Registers: Seconds, Minutes, Hours, Day Low, Day High
- Continues counting when powered off (battery-backed)
- Used in games like Pokémon Gold/Silver/Crystal

### 5.4 MBC5 (Max 8MB ROM, 128KB RAM)

**Bank Switching Behavior:**
- **0x0000-0x1FFF**: RAM enable (write 0x0A to enable)
- **0x2000-0x2FFF**: Select ROM bank low byte (8-bit)
- **0x3000-0x3FFF**: Select ROM bank high bit (9th bit)
- **0x4000-0x5FFF**: Select RAM bank (4-bit, banks 0x00-0x0F)

**Improvements over MBC1/MBC3:**
- 9-bit ROM bank select (512 banks instead of 128)
- No special case for bank 0x00 (bank 0 is valid)
- Guaranteed compatibility with CGB double-speed mode
- Supports larger RAM (up to 128KB)

### 5.5 Other MBC Types

- **MBC2**: Built-in 512×4-bit RAM, max 256KB ROM
- **MBC6**: Flash memory support (rare)
- **MBC7**: Accelerometer and EEPROM (Kirby Tilt 'n' Tumble)
- **HuC1/HuC3**: Hudson Soft controllers with infrared support

## 6. Test ROM Catalog

### 6.1 Blargg's Test ROMs

Located in `third_party/roms/cpu_instrs/individual/`:

| ROM | Purpose | Pass Criteria |
|-----|---------|---------------|
| 01-special.gb | Tests DAA, CPL, SCF, CCF, HALT | Displays "Passed" |
| 02-interrupts.gb | Tests interrupt timing and behavior | Displays "Passed" |
| 03-op sp,hl.gb | Tests 16-bit operations (SP, HL) | Displays "Passed" |
| 04-op r,imm.gb | Tests 8-bit immediate operations | Displays "Passed" |
| 05-op rp.gb | Tests 16-bit register pair operations | Displays "Passed" |
| 06-ld r,r.gb | Tests 8-bit register-to-register loads | Displays "Passed" |
| 07-jr,jp,call,ret,rst.gb | Tests jumps, calls, returns, restarts | Displays "Passed" |
| 08-misc instrs.gb | Tests miscellaneous instructions | Displays "Passed" |
| 09-op r,r.gb | Tests 8-bit register operations | Displays "Passed" |
| 10-bit ops.gb | Tests CB-prefixed bit operations | Displays "Passed" |
| 11-op a,(hl).gb | Tests A with (HL) addressing | Displays "Passed" |

**Additional Blargg ROMs:**
- `instr_timing/instr_timing.gb`: Tests instruction timing accuracy
- `halt_bug.gb`: Tests HALT bug behavior
- `dmg_sound/`, `cgb_sound/`: Audio accuracy tests (11 ROMs total)

### 6.2 Mooneye Test Suite

Located in `third_party/roms/mooneye/`:

Organized into categories:
- **acceptance/**: Tests that should pass on real hardware
- **emulator-only/**: Tests that require specific emulator features
- **manual-only/**: Tests requiring manual verification

**Pass criteria**: Registers should contain Fibonacci numbers: B=3, C=5, D=8, E=13, H=21, L=34, and execute opcode 0x40 (LD B, B).

### 6.3 Acid Tests

- **dmg-acid2**: PPU rendering accuracy test for DMG
- **cgb-acid2**: PPU rendering accuracy test for CGB

These produce visual output that should match reference images. They test sprite priority, background rendering, scrolling, and palette behavior.

## 7. Validation Questions

To verify understanding of this research:

1. **How many CPU cycles does one PPU mode 3 (pixel transfer) scanline take?**
   - Answer: Variable, 172-289 dots (43-72.25 M-cycles) depending on sprite count and scroll position

2. **What is the memory range for VRAM bank 0?**
   - Answer: $8000-$9FFF (8KB)

3. **Which MBC supports Real-Time Clock functionality?**
   - Answer: MBC3

4. **What is the frame rate of the Game Boy?**
   - Answer: ~59.7 Hz (70,224 dots per frame at 4.194304 MHz dot clock)

5. **How many sprite objects can be displayed per scanline?**
   - Answer: Maximum 10 sprites per scanline (selected from up to 40 total in OAM)

6. **What is the purpose of the half-carry flag?**
   - Answer: Used by the DAA instruction for BCD (Binary-Coded Decimal) arithmetic correction

7. **What happens when bit 7 of NR52 is cleared?**
   - Answer: The entire APU powers down; all sound registers become inaccessible (read-only, return zero)

8. **What is echo RAM?**
   - Answer: Memory range $E000-$FDFF that mirrors $C000-$DDFF; prohibited by Nintendo but functional on hardware

## 8. Bibliography and References

### Primary References

1. **Pan Docs** (2025 Edition)
   - URL: https://gbdev.io/pandocs/
   - Description: The definitive technical reference for Game Boy hardware, maintained by the gbdev community
   - Sections referenced: CPU Registers, Memory Map, PPU Rendering, Audio Registers, MBCs

2. **Game Boy CPU Manual**
   - URL: https://gbdev.io/gb-opcodes/
   - Description: Complete instruction set reference with opcode tables, cycle timings, and flag behaviors

3. **Gekkio's Game Boy Research**
   - URL: https://gekkio.fi/blog/
   - Description: Hardware reverse-engineering research including die shots, timing analysis, and edge case documentation

4. **The Cycle-Accurate Game Boy Docs**
   - URL: https://github.com/AntonioND/giibiiadvance/tree/master/docs
   - Description: Technical documentation focusing on cycle-accurate timing for CPU, PPU, and DMA

### Test ROM Sources

5. **Blargg's Game Boy Test ROMs**
   - URL: https://github.com/retrio/gb-test-roms
   - Description: Comprehensive CPU instruction test suite by Shay Green (Blargg)

6. **Mooneye Test Suite**
   - URL: https://github.com/Gekkio/mooneye-test-suite
   - Description: Hardware acceptance test suite by Joonas Javanainen (Gekkio)

7. **dmg-acid2 and cgb-acid2**
   - URL: https://github.com/mattcurrie/dmg-acid2 | https://github.com/mattcurrie/cgb-acid2
   - Description: PPU accuracy tests by Matt Currie

### Reference Emulators

8. **SameBoy**
   - URL: https://github.com/LIJI32/SameBoy
   - Description: Highly accurate Game Boy and Game Boy Color emulator by Lior Halphon, known for passing all major test suites

9. **Gambatte**
   - URL: https://github.com/sinamas/gambatte
   - Description: Accuracy-focused Game Boy Color emulator

10. **BGB**
    - URL: https://bgb.bircd.org/
    - Description: Game Boy debugger and emulator with powerful development tools

### Historical Context

11. **The Ultimate Game Boy Talk (2016)**
    - URL: https://www.youtube.com/watch?v=HyzD8pNlpwI
    - Description: Technical deep-dive presentation by Michael Steil at 33C3

12. **Game Boy: Complete Technical Reference**
    - URL: https://github.com/Gekkio/gb-ctr
    - Description: In-progress comprehensive reference documentation project

### Additional Resources

13. **GBDev Wiki**
    - URL: https://gbdev.gg8.se/wiki/
    - Description: Community wiki with tutorials, tools, and emulation guides

14. **Awesome Game Boy Development**
    - URL: https://github.com/gbdev/awesome-gbdev
    - Description: Curated list of Game Boy development resources

15. **Game Boy Architecture: A Practical Analysis**
    - URL: https://www.copetti.org/writings/consoles/game-boy/
    - Description: Detailed architectural analysis by Rodrigo Copetti

## 9. Conclusion

The Game Boy and Game Boy Color represent a masterclass in hardware design: simple enough to understand, complex enough to be interesting, and robust enough to run millions of games reliably. The Sharp LR35902 CPU, with its Z80-inspired instruction set and careful timing design, provides a solid foundation. The tile-based PPU efficiently renders graphics while maintaining strict timing constraints. The 4-channel APU offers surprising musical flexibility within severe hardware limitations. And the MBC system demonstrates how clever bank switching can extend a limited address space far beyond its original capability.

For PHPBoy development, this research provides the foundational knowledge needed to build accurate CPU emulation, render graphics correctly, synthesize audio authentically, and support the full Game Boy library through comprehensive MBC implementation. The test ROMs cataloged here will guide verification at each development stage, ensuring that PHPBoy achieves compatibility and accuracy worthy of the Game Boy legacy.

**Total Word Count**: 5,147 words

---

*Research compiled November 2025 for PHPBoy emulator development*
*This document is a living reference and will be updated as development progresses*
