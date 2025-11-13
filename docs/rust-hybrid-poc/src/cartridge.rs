//! Cartridge handling
//!
//! Parses ROM headers and handles Memory Bank Controllers (MBC).

/// Cartridge types
#[derive(Debug, Clone, Copy, PartialEq)]
pub enum CartridgeType {
    RomOnly,
    Mbc1,
    Mbc3,
    Mbc5,
    Unknown(u8),
}

/// Cartridge header
pub struct CartridgeHeader {
    pub title: String,
    pub cartridge_type: CartridgeType,
    pub rom_size: usize,
    pub ram_size: usize,
    pub cgb_flag: u8,
}

/// Cartridge (ROM + optional RAM)
pub struct Cartridge {
    pub header: CartridgeHeader,
    pub rom: Vec<u8>,
    pub ram: Vec<u8>,
}

impl Cartridge {
    /// Create cartridge from ROM data
    pub fn from_rom(data: &[u8]) -> Result<Self, String> {
        if data.len() < 0x150 {
            return Err("ROM too small".to_string());
        }

        let header = Self::parse_header(data)?;
        let ram = vec![0; header.ram_size];

        Ok(Cartridge {
            header,
            rom: data.to_vec(),
            ram,
        })
    }

    /// Parse cartridge header
    fn parse_header(data: &[u8]) -> Result<CartridgeHeader, String> {
        // Title at 0x134-0x143
        let title_bytes = &data[0x134..0x144];
        let title = String::from_utf8_lossy(title_bytes)
            .trim_end_matches('\0')
            .to_string();

        // CGB flag at 0x143
        let cgb_flag = data[0x143];

        // Cartridge type at 0x147
        let cart_type_byte = data[0x147];
        let cartridge_type = match cart_type_byte {
            0x00 => CartridgeType::RomOnly,
            0x01..=0x03 => CartridgeType::Mbc1,
            0x0F..=0x13 => CartridgeType::Mbc3,
            0x19..=0x1E => CartridgeType::Mbc5,
            _ => CartridgeType::Unknown(cart_type_byte),
        };

        // ROM size at 0x148
        let rom_size_byte = data[0x148];
        let rom_size = 32768 << rom_size_byte;

        // RAM size at 0x149
        let ram_size_byte = data[0x149];
        let ram_size = match ram_size_byte {
            0x00 => 0,
            0x02 => 8192,
            0x03 => 32768,
            0x04 => 131072,
            0x05 => 65536,
            _ => 0,
        };

        Ok(CartridgeHeader {
            title,
            cartridge_type,
            rom_size,
            ram_size,
            cgb_flag,
        })
    }
}

#[cfg(test)]
mod tests {
    use super::*;

    #[test]
    fn test_parse_header() {
        let mut rom = vec![0u8; 0x8000];

        // Set title
        rom[0x134..0x140].copy_from_slice(b"TESTROM");

        // Set cart type (ROM only)
        rom[0x147] = 0x00;

        // Set ROM size (32 KB)
        rom[0x148] = 0x00;

        // Set RAM size (none)
        rom[0x149] = 0x00;

        let cart = Cartridge::from_rom(&rom).unwrap();
        assert_eq!(cart.header.title, "TESTROM");
        assert_eq!(cart.header.cartridge_type, CartridgeType::RomOnly);
        assert_eq!(cart.header.rom_size, 32768);
        assert_eq!(cart.header.ram_size, 0);
    }
}
