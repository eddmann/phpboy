# PHPBoy - Game Boy Color Emulator

A readable, well-architected Game Boy Color (GBC) emulator written in PHP 8.5 that runs in the CLI and, via WebAssembly, in the browser.

## Features

- **Modern PHP 8.5 RC**: Leverages the latest PHP 8.5 release candidate features including strict types, readonly properties, enums, typed class constants, and property hooks
- **Fully Dockerized Development**: All PHP/Composer/testing tools run exclusively in Docker containers for consistency
- **Browser Support**: Run PHPBoy in your browser via WebAssembly (Step 15)
- **Comprehensive Testing**: PHPUnit 10 for unit and integration tests with 100% Blargg test pass rate
- **Static Analysis**: PHPStan at maximum level (9) for type safety
- **Modular Architecture**: Clean separation of concerns with dedicated namespaces for CPU, PPU, APU, Bus, and Frontend
- **Complete Emulation**: Full CPU (LR35902), PPU with sprites/background/window, APU with 4 channels, MBC1/3/5 cartridge support

## Requirements

- Docker
- Docker Compose
- Make (for convenient task automation)

**Important**: All PHP, Composer, PHPUnit, and PHPStan commands must run through Docker. Never run these tools directly on the host machine.

## Getting Started

### Initial Setup

1. Clone the repository:
```bash
git clone <repository-url>
cd phpboy
```

2. Build the Docker image:
```bash
make setup
```

3. Install PHP dependencies:
```bash
make install
```

### Development Workflow

All development tasks are managed through the Makefile and run inside Docker containers. **Never run PHP, Composer, PHPUnit, or PHPStan directly on the host machine.**

#### Available Commands

- `make help` - Show available commands
- `make setup` - Build Docker image with PHP 8.5 RC4
- `make rebuild` - Rebuild Docker image from scratch (no cache)
- `make install` - Install Composer dependencies in Docker
- `make test` - Run PHPUnit tests in Docker
- `make lint` - Run PHPStan static analysis in Docker
- `make shell` - Open bash shell in Docker container
- `make run ROM=path/to/rom.gb` - Run emulator with specified ROM in Docker (coming soon)
- `make clean` - Remove vendor directory and composer.lock
- `make clean-docker` - Remove Docker containers and images

#### Running Tests

```bash
make test
```

#### Static Analysis

```bash
make lint
```

#### Opening a Shell

For debugging or manual operations:
```bash
make shell
```

## Browser Version (WebAssembly)

PHPBoy can run entirely in your browser using WebAssembly! Play Game Boy games without installing anything.

### Quick Start (Browser)

1. **Build for browser:**
```bash
make build-wasm
```

2. **Serve locally:**
```bash
make serve-wasm
```

3. **Open browser:**
   - Navigate to `http://localhost:8000`
   - Click "Choose ROM File" and select a .gb or .gbc ROM
   - Click "Play" and enjoy!

### Browser Controls

| Key | Game Boy Button |
|-----|----------------|
| Arrow Keys | D-Pad |
| Z | A Button |
| X | B Button |
| Enter | Start |
| Shift | Select |

### Browser Build Commands

- `make build-wasm` - Build WebAssembly version
- `make serve-wasm` - Serve locally at http://localhost:8000
- `make wasm-info` - Show WASM build information

### Documentation

- [Browser Usage Guide](docs/browser-usage.md) - How to use PHPBoy in your browser
- [WASM Build Guide](docs/wasm-build.md) - How to build the WebAssembly version
- [WASM Options Research](docs/wasm-options.md) - Technical research on PHP-to-WASM approaches

**Note:** The WebAssembly build requires php-wasm (Emscripten-compiled PHP). See [docs/wasm-build.md](docs/wasm-build.md) for setup instructions.

## Project Structure

```
phpboy/
├── bin/                    # CLI entry point
├── docs/                   # Documentation
│   ├── research.md        # Game Boy hardware research
│   ├── wasm-options.md    # PHP-to-WASM research
│   ├── wasm-build.md      # WebAssembly build guide
│   └── browser-usage.md   # Browser usage instructions
├── src/                   # Source code
│   ├── Apu/              # Audio Processing Unit
│   ├── Bus/              # Memory bus
│   ├── Cartridge/        # ROM/MBC handling
│   ├── Cpu/              # CPU emulation
│   ├── Frontend/         # CLI and WASM frontends
│   │   ├── Cli/          # CLI renderer & input
│   │   └── Wasm/         # Browser framebuffer & input
│   ├── Ppu/              # Pixel Processing Unit
│   └── Support/          # Utilities and helpers
├── tests/                # Test suite
│   ├── Integration/      # Integration tests
│   └── Unit/            # Unit tests
├── third_party/          # External resources
│   ├── references/       # Technical documentation
│   └── roms/            # Test ROMs
├── web/                  # Browser frontend (WebAssembly)
│   ├── index.html        # Web UI
│   ├── styles.css        # UI styles
│   └── js/               # JavaScript bridge & controller
│       ├── phpboy.js     # WASM/PHP bridge
│       └── app.js        # UI controller
├── composer.json         # PHP dependencies
├── Dockerfile           # Docker image definition
├── docker-compose.yml   # Docker services
├── Makefile            # Task automation
├── phpstan.neon        # PHPStan configuration
├── phpunit.xml         # PHPUnit configuration
└── PLAN.md             # Development roadmap
```

## Development Philosophy

PHPBoy follows a step-by-step development approach, implementing each Game Boy subsystem incrementally. Each step includes:

- Historical context about the Game Boy hardware
- Clear implementation tasks
- Comprehensive tests
- Documentation updates
- Verification criteria

See `PLAN.md` for the complete development roadmap.

## Testing

PHPBoy uses industry-standard test ROMs to verify accuracy:

- **Blargg's test suite**: CPU instruction validation
- **Mooneye test suite**: Hardware behavior verification
- **dmg-acid2/cgb-acid2**: PPU rendering accuracy

## License

MIT License

## Contributing

Contributions are welcome! Please ensure:

1. All code passes PHPStan level 9
2. Tests are included for new features
3. Follow conventional commits format
4. Use the Makefile for all operations
