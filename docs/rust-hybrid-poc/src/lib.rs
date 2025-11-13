//! PHPBoy Core - Rust/WASM Implementation
//!
//! High-performance Game Boy emulator core compiled to WebAssembly.
//! Designed for 60+ FPS in browser with zero-copy data transfer.

use wasm_bindgen::prelude::*;
use js_sys::{Uint8Array, Uint8ClampedArray, Float32Array};

mod cpu;
mod ppu;
mod bus;
mod cartridge;

use cpu::Cpu;
use ppu::Ppu;
use bus::Bus;
use cartridge::Cartridge;

/// Screen dimensions
const SCREEN_WIDTH: usize = 160;
const SCREEN_HEIGHT: usize = 144;
const SCREEN_PIXELS: usize = SCREEN_WIDTH * SCREEN_HEIGHT * 4; // RGBA

/// CPU cycles per frame (59.7 Hz)
const CYCLES_PER_FRAME: u32 = 70224;

/// Game Boy emulator core
///
/// This is the main entry point for JavaScript. It manages the emulation
/// state and provides a simple API for running frames and accessing data.
#[wasm_bindgen]
pub struct GameBoyCore {
    cpu: Cpu,
    ppu: Ppu,
    bus: Bus,
    cartridge: Option<Cartridge>,
    framebuffer: Box<[u8; SCREEN_PIXELS]>,
    audio_buffer: Vec<f32>,
    cycle_count: u32,
}

#[wasm_bindgen]
impl GameBoyCore {
    /// Create a new Game Boy emulator instance
    #[wasm_bindgen(constructor)]
    pub fn new() -> Result<GameBoyCore, JsValue> {
        // Set up better panic messages in console
        console_error_panic_hook::set_once();

        // Initialize with default state
        let bus = Bus::new();
        let cpu = Cpu::new();
        let ppu = Ppu::new();

        Ok(GameBoyCore {
            cpu,
            ppu,
            bus,
            cartridge: None,
            framebuffer: Box::new([0u8; SCREEN_PIXELS]),
            audio_buffer: Vec::with_capacity(4096),
            cycle_count: 0,
        })
    }

    /// Load a ROM file into the emulator
    ///
    /// # Arguments
    /// * `rom_data` - Byte array containing the ROM file
    ///
    /// # Errors
    /// Returns error if ROM is invalid or unsupported
    #[wasm_bindgen]
    pub fn load_rom(&mut self, rom_data: &[u8]) -> Result<(), JsValue> {
        let cartridge = Cartridge::from_rom(rom_data)
            .map_err(|e| JsValue::from_str(&format!("Failed to load ROM: {}", e)))?;

        self.cartridge = Some(cartridge);
        self.reset();

        Ok(())
    }

    /// Execute one frame of emulation (70224 cycles)
    ///
    /// This runs the CPU for exactly one frame's worth of cycles,
    /// updating the PPU and generating pixel + audio data.
    #[wasm_bindgen]
    pub fn step(&mut self) {
        let mut cycles_this_frame = 0;

        while cycles_this_frame < CYCLES_PER_FRAME {
            // Execute one CPU instruction
            let cycles = self.cpu.step(&mut self.bus);

            // Update PPU (generates pixels)
            self.ppu.step(cycles, &mut self.framebuffer);

            // TODO: Update APU (generates audio)

            cycles_this_frame += cycles;
            self.cycle_count += cycles;
        }
    }

    /// Get the framebuffer as a Uint8ClampedArray (zero-copy)
    ///
    /// Returns a view into the WASM linear memory containing RGBA pixel data.
    /// This is zero-copy - JavaScript directly accesses WASM memory.
    ///
    /// Format: [r,g,b,a, r,g,b,a, ...] for 160×144 pixels
    #[wasm_bindgen]
    pub fn get_pixels(&self) -> Uint8ClampedArray {
        // SAFETY: This creates a view into WASM memory. The buffer is owned
        // by this struct and won't be freed while the view exists (within same frame).
        unsafe {
            Uint8ClampedArray::view(&self.framebuffer[..])
        }
    }

    /// Get audio samples as Float32Array (zero-copy)
    ///
    /// Returns audio samples in range [-1.0, 1.0] at 32768 Hz.
    #[wasm_bindgen]
    pub fn get_audio(&self) -> Float32Array {
        unsafe {
            Float32Array::view(&self.audio_buffer[..])
        }
    }

    /// Set button state
    ///
    /// # Arguments
    /// * `button` - Button code (0=A, 1=B, 2=Start, 3=Select, 4=Up, 5=Down, 6=Left, 7=Right)
    /// * `pressed` - true if button is pressed, false if released
    #[wasm_bindgen]
    pub fn set_input(&mut self, button: u8, pressed: bool) {
        self.bus.set_button(button, pressed);
    }

    /// Reset the emulator to initial state
    #[wasm_bindgen]
    pub fn reset(&mut self) {
        self.cpu.reset();
        self.ppu.reset();
        self.bus.reset();
        self.cycle_count = 0;
        self.framebuffer.fill(255); // White screen
        self.audio_buffer.clear();
    }

    /// Get serialized state for save states
    ///
    /// Returns a byte array containing all emulator state.
    /// Can be stored in localStorage and restored later.
    #[wasm_bindgen]
    pub fn get_state(&self) -> Vec<u8> {
        // TODO: Implement proper serialization
        // For now, return empty vec
        Vec::new()
    }

    /// Restore from serialized state
    ///
    /// # Arguments
    /// * `state` - Byte array from previous get_state() call
    #[wasm_bindgen]
    pub fn set_state(&mut self, _state: &[u8]) -> Result<(), JsValue> {
        // TODO: Implement deserialization
        Ok(())
    }

    /// Get cycle count (for debugging/profiling)
    #[wasm_bindgen]
    pub fn get_cycles(&self) -> u32 {
        self.cycle_count
    }

    /// Get memory pointer (for advanced zero-copy access)
    ///
    /// Returns the base address of the framebuffer in WASM linear memory.
    /// Advanced usage only - prefer get_pixels() for normal use.
    #[wasm_bindgen]
    pub fn get_framebuffer_ptr(&self) -> *const u8 {
        self.framebuffer.as_ptr()
    }
}

/// Performance benchmarking function
///
/// Runs the emulator for N frames and reports timing.
/// Useful for comparing implementations.
#[wasm_bindgen]
pub fn benchmark(frames: u32) -> f64 {
    let mut core = GameBoyCore::new().unwrap();

    // Create dummy ROM
    let dummy_rom = vec![0u8; 32768];
    let _ = core.load_rom(&dummy_rom);

    // Get performance.now()
    let window = web_sys::window().unwrap();
    let performance = window.performance().unwrap();

    let start = performance.now();

    for _ in 0..frames {
        core.step();
    }

    let end = performance.now();
    end - start
}

/// Version string
#[wasm_bindgen]
pub fn version() -> String {
    env!("CARGO_PKG_VERSION").to_string()
}
