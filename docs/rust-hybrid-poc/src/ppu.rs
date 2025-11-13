//! Game Boy PPU (Pixel Processing Unit)
//!
//! Handles all video rendering: background, window, sprites.
//! Operates in sync with CPU at 4.194304 MHz.

/// PPU modes
#[derive(Clone, Copy, PartialEq)]
enum Mode {
    HBlank = 0,
    VBlank = 1,
    OamSearch = 2,
    Drawing = 3,
}

/// PPU state
pub struct Ppu {
    mode: Mode,
    cycle: u32,
    scanline: u8,
    lcdc: u8,  // LCD Control
    stat: u8,  // LCD Status
    scy: u8,   // Scroll Y
    scx: u8,   // Scroll X
    ly: u8,    // Current scanline
    lyc: u8,   // LY Compare
    bgp: u8,   // BG Palette
    obp0: u8,  // OBJ Palette 0
    obp1: u8,  // OBJ Palette 1
}

impl Ppu {
    pub fn new() -> Self {
        Ppu {
            mode: Mode::OamSearch,
            cycle: 0,
            scanline: 0,
            lcdc: 0x91,
            stat: 0x00,
            scy: 0,
            scx: 0,
            ly: 0,
            lyc: 0,
            bgp: 0xFC,
            obp0: 0xFF,
            obp1: 0xFF,
        }
    }

    pub fn reset(&mut self) {
        *self = Self::new();
    }

    /// Step the PPU for the given number of cycles
    pub fn step(&mut self, cycles: u32, framebuffer: &mut [u8]) {
        for _ in 0..cycles {
            self.cycle += 1;

            match self.mode {
                Mode::OamSearch => {
                    if self.cycle >= 80 {
                        self.mode = Mode::Drawing;
                        self.cycle = 0;
                    }
                }

                Mode::Drawing => {
                    if self.cycle >= 172 {
                        // Render scanline
                        self.render_scanline(framebuffer);

                        self.mode = Mode::HBlank;
                        self.cycle = 0;
                    }
                }

                Mode::HBlank => {
                    if self.cycle >= 204 {
                        self.scanline += 1;
                        self.ly = self.scanline;
                        self.cycle = 0;

                        if self.scanline >= 144 {
                            // Enter VBlank
                            self.mode = Mode::VBlank;
                        } else {
                            self.mode = Mode::OamSearch;
                        }
                    }
                }

                Mode::VBlank => {
                    if self.cycle >= 456 {
                        self.scanline += 1;
                        self.ly = self.scanline;
                        self.cycle = 0;

                        if self.scanline >= 154 {
                            // End of frame
                            self.scanline = 0;
                            self.ly = 0;
                            self.mode = Mode::OamSearch;
                        }
                    }
                }
            }
        }
    }

    /// Render a single scanline to the framebuffer
    fn render_scanline(&self, framebuffer: &mut [u8]) {
        let y = self.scanline as usize;
        if y >= 144 {
            return;
        }

        // Simple background rendering (proof-of-concept)
        for x in 0..160 {
            let offset = (y * 160 + x) * 4;

            // For now, just render a test pattern
            let color = ((x + y) % 4) as u8;
            let rgb = self.dmg_color(color, self.bgp);

            framebuffer[offset] = rgb.0;
            framebuffer[offset + 1] = rgb.1;
            framebuffer[offset + 2] = rgb.2;
            framebuffer[offset + 3] = 255;
        }
    }

    /// Convert DMG palette color to RGB
    fn dmg_color(&self, color: u8, palette: u8) -> (u8, u8, u8) {
        let shade = (palette >> (color * 2)) & 0x03;

        match shade {
            0 => (255, 255, 255),  // White
            1 => (192, 192, 192),  // Light gray
            2 => (96, 96, 96),     // Dark gray
            3 => (0, 0, 0),        // Black
            _ => unreachable!(),
        }
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_ppu_init() {
        let ppu = Ppu::new();
        assert_eq!(ppu.scanline, 0);
        assert_eq!(ppu.mode, Mode::OamSearch);
    }

    #[test]
    fn test_mode_transitions() {
        let mut ppu = Ppu::new();
        let mut fb = vec![0u8; 160 * 144 * 4];

        // OAM Search (80 cycles)
        ppu.step(80, &mut fb);
        assert_eq!(ppu.mode, Mode::Drawing);

        // Drawing (172 cycles)
        ppu.step(172, &mut fb);
        assert_eq!(ppu.mode, Mode::HBlank);

        // HBlank (204 cycles)
        ppu.step(204, &mut fb);
        assert_eq!(ppu.mode, Mode::OamSearch);
        assert_eq!(ppu.scanline, 1);
    }
}
