//! Game Boy CPU (LR35902 / Sharp SM83)
//!
//! 8-bit CPU with 16-bit address space, similar to Z80 but with some differences.

use crate::bus::Bus;

/// CPU registers
pub struct Registers {
    pub a: u8,
    pub f: u8,  // Flags: Z N H C 0 0 0 0
    pub b: u8,
    pub c: u8,
    pub d: u8,
    pub e: u8,
    pub h: u8,
    pub l: u8,
    pub sp: u16,
    pub pc: u16,
}

/// CPU flags
const FLAG_Z: u8 = 0b1000_0000;  // Zero
const FLAG_N: u8 = 0b0100_0000;  // Subtraction
const FLAG_H: u8 = 0b0010_0000;  // Half-carry
const FLAG_C: u8 = 0b0001_0000;  // Carry

/// Game Boy CPU
pub struct Cpu {
    regs: Registers,
    ime: bool,  // Interrupt Master Enable
    halted: bool,
}

impl Cpu {
    pub fn new() -> Self {
        Cpu {
            regs: Registers {
                a: 0x01,
                f: 0xB0,
                b: 0x00,
                c: 0x13,
                d: 0x00,
                e: 0xD8,
                h: 0x01,
                l: 0x4D,
                sp: 0xFFFE,
                pc: 0x0100,
            },
            ime: false,
            halted: false,
        }
    }

    pub fn reset(&mut self) {
        *self = Self::new();
    }

    /// Execute one instruction and return cycles consumed
    pub fn step(&mut self, bus: &mut Bus) -> u32 {
        if self.halted {
            return 4;
        }

        // Fetch opcode
        let opcode = bus.read(self.regs.pc);
        self.regs.pc = self.regs.pc.wrapping_add(1);

        // Decode and execute
        self.execute(opcode, bus)
    }

    /// Execute a single instruction
    fn execute(&mut self, opcode: u8, bus: &mut Bus) -> u32 {
        match opcode {
            // NOP
            0x00 => 4,

            // LD BC, nn
            0x01 => {
                let low = bus.read(self.regs.pc);
                self.regs.pc = self.regs.pc.wrapping_add(1);
                let high = bus.read(self.regs.pc);
                self.regs.pc = self.regs.pc.wrapping_add(1);
                self.regs.b = high;
                self.regs.c = low;
                12
            }

            // LD (BC), A
            0x02 => {
                let addr = u16::from_be_bytes([self.regs.b, self.regs.c]);
                bus.write(addr, self.regs.a);
                8
            }

            // INC BC
            0x03 => {
                let bc = u16::from_be_bytes([self.regs.b, self.regs.c]).wrapping_add(1);
                self.regs.b = (bc >> 8) as u8;
                self.regs.c = bc as u8;
                8
            }

            // INC B
            0x04 => {
                self.regs.b = self.inc(self.regs.b);
                4
            }

            // DEC B
            0x05 => {
                self.regs.b = self.dec(self.regs.b);
                4
            }

            // LD B, n
            0x06 => {
                self.regs.b = bus.read(self.regs.pc);
                self.regs.pc = self.regs.pc.wrapping_add(1);
                8
            }

            // RLCA
            0x07 => {
                let carry = (self.regs.a & 0x80) >> 7;
                self.regs.a = (self.regs.a << 1) | carry;
                self.regs.f = if carry != 0 { FLAG_C } else { 0 };
                4
            }

            // ... (complete instruction set would go here)

            // For proof-of-concept, return default cycles
            _ => {
                // Unknown opcode - skip it
                4
            }
        }
    }

    /// Increment with flags
    fn inc(&mut self, val: u8) -> u8 {
        let result = val.wrapping_add(1);

        self.regs.f = (self.regs.f & FLAG_C) |  // Preserve carry
            if result == 0 { FLAG_Z } else { 0 } |
            if (val & 0x0F) == 0x0F { FLAG_H } else { 0 };

        result
    }

    /// Decrement with flags
    fn dec(&mut self, val: u8) -> u8 {
        let result = val.wrapping_sub(1);

        self.regs.f = (self.regs.f & FLAG_C) |  // Preserve carry
            FLAG_N |
            if result == 0 { FLAG_Z } else { 0 } |
            if (val & 0x0F) == 0 { FLAG_H } else { 0 };

        result
    }

    // Helper methods for register pairs
    fn bc(&self) -> u16 {
        u16::from_be_bytes([self.regs.b, self.regs.c])
    }

    fn de(&self) -> u16 {
        u16::from_be_bytes([self.regs.d, self.regs.e])
    }

    fn hl(&self) -> u16 {
        u16::from_be_bytes([self.regs.h, self.regs.l])
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_cpu_init() {
        let cpu = Cpu::new();
        assert_eq!(cpu.regs.pc, 0x0100);
        assert_eq!(cpu.regs.sp, 0xFFFE);
    }

    #[test]
    fn test_inc() {
        let mut cpu = Cpu::new();
        let result = cpu.inc(0x00);
        assert_eq!(result, 0x01);
        assert_eq!(cpu.regs.f & FLAG_Z, 0);

        let result = cpu.inc(0xFF);
        assert_eq!(result, 0x00);
        assert_ne!(cpu.regs.f & FLAG_Z, 0);
    }
}
