//! Memory Bus
//!
//! Handles all memory reads/writes with proper mapping:
//! - 0x0000-0x7FFF: ROM
//! - 0x8000-0x9FFF: VRAM
//! - 0xA000-0xBFFF: External RAM
//! - 0xC000-0xDFFF: Work RAM
//! - 0xFE00-0xFE9F: OAM
//! - 0xFF00-0xFF7F: I/O Registers
//! - 0xFF80-0xFFFE: High RAM

pub struct Bus {
    rom: Vec<u8>,
    vram: [u8; 8192],
    wram: [u8; 8192],
    hram: [u8; 127],
    oam: [u8; 160],
    io: [u8; 128],
    buttons: u8,
}

impl Bus {
    pub fn new() -> Self {
        Bus {
            rom: vec![0; 32768],
            vram: [0; 8192],
            wram: [0; 8192],
            hram: [0; 127],
            oam: [0; 160],
            io: [0; 128],
            buttons: 0xFF,  // All buttons released
        }
    }

    pub fn reset(&mut self) {
        self.vram.fill(0);
        self.wram.fill(0);
        self.hram.fill(0);
        self.oam.fill(0);
        self.io.fill(0);
        self.buttons = 0xFF;
    }

    pub fn read(&self, addr: u16) -> u8 {
        match addr {
            // ROM
            0x0000..=0x7FFF => {
                let offset = addr as usize;
                if offset < self.rom.len() {
                    self.rom[offset]
                } else {
                    0xFF
                }
            }

            // VRAM
            0x8000..=0x9FFF => self.vram[(addr - 0x8000) as usize],

            // External RAM (not implemented yet)
            0xA000..=0xBFFF => 0xFF,

            // Work RAM
            0xC000..=0xDFFF => self.wram[(addr - 0xC000) as usize],

            // Echo RAM (mirrors WRAM)
            0xE000..=0xFDFF => self.wram[(addr - 0xE000) as usize],

            // OAM
            0xFE00..=0xFE9F => self.oam[(addr - 0xFE00) as usize],

            // Unusable
            0xFEA0..=0xFEFF => 0xFF,

            // I/O Registers
            0xFF00..=0xFF7F => {
                if addr == 0xFF00 {
                    // Joypad register
                    self.buttons
                } else {
                    self.io[(addr - 0xFF00) as usize]
                }
            }

            // High RAM
            0xFF80..=0xFFFE => self.hram[(addr - 0xFF80) as usize],

            // Interrupt Enable
            0xFFFF => self.io[0x7F],

            _ => 0xFF,
        }
    }

    pub fn write(&mut self, addr: u16, value: u8) {
        match addr {
            // ROM (read-only, but MBC commands go here)
            0x0000..=0x7FFF => {
                // TODO: Handle MBC commands
            }

            // VRAM
            0x8000..=0x9FFF => self.vram[(addr - 0x8000) as usize] = value,

            // External RAM
            0xA000..=0xBFFF => {
                // TODO: Handle cartridge RAM
            }

            // Work RAM
            0xC000..=0xDFFF => self.wram[(addr - 0xC000) as usize] = value,

            // Echo RAM
            0xE000..=0xFDFF => self.wram[(addr - 0xE000) as usize] = value,

            // OAM
            0xFE00..=0xFE9F => self.oam[(addr - 0xFE00) as usize] = value,

            // Unusable
            0xFEA0..=0xFEFF => {}

            // I/O Registers
            0xFF00..=0xFF7F => self.io[(addr - 0xFF00) as usize] = value,

            // High RAM
            0xFF80..=0xFFFE => self.hram[(addr - 0xFF80) as usize] = value,

            // Interrupt Enable
            0xFFFF => self.io[0x7F] = value,

            _ => {}
        }
    }

    pub fn set_button(&mut self, button: u8, pressed: bool) {
        if button < 8 {
            if pressed {
                self.buttons &= !(1 << button);
            } else {
                self.buttons |= 1 << button;
            }
        }
    }

    pub fn load_rom(&mut self, data: &[u8]) {
        self.rom = data.to_vec();
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_bus_read_write() {
        let mut bus = Bus::new();

        bus.write(0xC000, 0x42);
        assert_eq!(bus.read(0xC000), 0x42);

        // Test echo RAM
        assert_eq!(bus.read(0xE000), 0x42);
    }

    #[test]
    fn test_button_input() {
        let mut bus = Bus::new();

        bus.set_button(0, true);  // Press A
        assert_eq!(bus.buttons & 0x01, 0x00);

        bus.set_button(0, false);  // Release A
        assert_eq!(bus.buttons & 0x01, 0x01);
    }
}
