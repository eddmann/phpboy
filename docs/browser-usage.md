# PHPBoy Browser Usage Guide

This guide explains how to use PHPBoy in the browser.

## Getting Started

### Accessing PHPBoy

You can access PHPBoy in two ways:

1. **Local Development**: Run `make serve-wasm` after building
2. **Deployed Version**: Visit the hosted version (if available)

### Loading a ROM

1. Click the **"Choose ROM File"** button
2. Select a `.gb` (Game Boy) or `.gbc` (Game Boy Color) ROM file
3. The emulator will load and automatically start running
4. You should see the game rendered on the screen

**Note**: You must provide your own legally obtained ROM files. PHPBoy does not include any ROMs.

## Controls

### Keyboard Controls

PHPBoy maps keyboard keys to Game Boy buttons:

| Keyboard Key | Game Boy Button |
|--------------|-----------------|
| **Arrow Up** | D-pad Up |
| **Arrow Down** | D-pad Down |
| **Arrow Left** | D-pad Left |
| **Arrow Right** | D-pad Right |
| **Z** or **A** | A Button |
| **X** or **S** | B Button |
| **Enter** | Start |
| **Shift** | Select |

### On-Screen Controls (Mobile)

On mobile devices and tablets, touch-friendly on-screen controls appear automatically:

- **D-pad**: Directional buttons on the left
- **A/B buttons**: Action buttons on the right
- **Start/Select**: Menu buttons at the bottom

## Emulator Controls

### Pause/Resume

- Click the **"Pause"** button to pause emulation
- Click **"Resume"** to continue
- Game state is preserved while paused

### Reset

- Click the **"Reset"** button to restart the game
- This is equivalent to pressing the reset button on a real Game Boy

### Speed Control

Adjust emulation speed using the dropdown:

- **0.5x**: Half speed (useful for difficult sections)
- **1.0x**: Normal speed (default, accurate to real hardware)
- **2.0x**: Double speed (fast-forward)
- **4.0x**: Quad speed (very fast)

### Volume Control

Use the slider to adjust audio volume from 0% to 100%.

**Note**: Audio implementation is basic and may have quality issues.

## Performance

### FPS Counter

The FPS (frames per second) counter shows current emulation speed:

- **60 FPS**: Running at full speed (ideal)
- **30-59 FPS**: Running slower than intended
- **>60 FPS**: Running faster than intended (with speed multiplier)

### Performance Tips

If the emulator runs slowly:

1. **Close other browser tabs** to free up resources
2. **Try a different browser** (Chrome typically performs best)
3. **Lower the speed multiplier** to reduce CPU usage
4. **Disable other background applications**

## Browser Compatibility

PHPBoy requires a modern browser with WebAssembly support:

### Supported Browsers

- ✅ **Chrome 90+** (recommended, best performance)
- ✅ **Firefox 88+**
- ✅ **Safari 14+**
- ✅ **Edge 90+**

### Unsupported Browsers

- ❌ Internet Explorer (no WebAssembly support)
- ❌ Very old browsers (< 2 years old)

## Features

### What Works

- ✅ Full Game Boy and Game Boy Color emulation
- ✅ CPU, PPU, APU emulation
- ✅ Graphics rendering
- ✅ Keyboard input
- ✅ Speed control
- ✅ Pause/Resume
- ✅ Multiple MBC types (MBC1, MBC3, MBC5)

### Known Limitations

- ⚠️ **Audio**: Basic implementation, may have quality issues
- ⚠️ **Save Files**: Not persisted between browser sessions
- ⚠️ **Performance**: May be slower than native apps
- ⚠️ **Link Cable**: Multiplayer not supported

## Troubleshooting

### ROM Won't Load

**Problem**: Error message when loading a ROM

**Solutions**:
- Ensure the file is a valid `.gb` or `.gbc` ROM
- Try a different ROM file
- Check browser console for error messages
- Refresh the page and try again

### Black Screen

**Problem**: Screen stays black after loading ROM

**Solutions**:
- Wait a few seconds for PHP-WASM to initialize
- Check FPS counter - if it's 0, the emulator isn't running
- Try clicking Pause then Resume
- Refresh the page and reload the ROM

### No Audio

**Problem**: Game runs but no sound

**Solutions**:
- Check volume slider is not at 0%
- Check browser tab isn't muted
- Audio implementation is basic - some games may not work
- Try refreshing the page

### Slow Performance

**Problem**: Game runs at <60 FPS

**Solutions**:
- Close other browser tabs
- Try Chrome (usually faster than Firefox/Safari)
- Reduce speed multiplier if needed
- Disable other background applications
- Try a simpler game (some games are more demanding)

### Controls Not Working

**Problem**: Keyboard keys don't respond

**Solutions**:
- Click on the page to ensure it has focus
- Check keyboard layout (some keys may differ)
- Try alternative keys (Z or A for A button)
- Use on-screen controls on mobile

## Tips & Tricks

### Best Practices

1. **Wait for Loading**: Give PHP-WASM a few seconds to initialize
2. **Test with Simple ROMs**: Start with well-known games (Tetris, etc.)
3. **Use Chrome**: Generally provides best performance
4. **Keyboard Focus**: Click on the page if controls stop working

### Recommended Games

Good games to test with:

- **Tetris**: Simple, runs perfectly
- **Dr. Mario**: Light puzzle game
- **Pokémon Red/Blue**: Complex, good test of features
- **Kirby's Dream Land**: Fast-paced action

### Performance Expectations

Approximate performance on different devices:

- **Modern Desktop** (2020+): 60 FPS consistently
- **Laptop**: 50-60 FPS
- **Tablet**: 40-60 FPS (depends on CPU)
- **Phone**: 30-50 FPS (may struggle)

## Privacy & Security

### Data Storage

- **ROMs**: Loaded into browser memory, not uploaded to any server
- **Save Files**: Currently not persisted (in-memory only)
- **No Tracking**: PHPBoy doesn't collect any user data

### Offline Use

Once loaded, PHPBoy can run offline. The PHP-WASM runtime and all code are cached by your browser.

## Legal Notes

### ROMs

You must provide your own ROM files. It is your responsibility to ensure you have the legal right to use any ROMs you load into the emulator.

### Open Source

PHPBoy is open source software. See the GitHub repository for the full source code and license.

## Support

### Getting Help

If you encounter issues:

1. Check this documentation
2. Check the browser console for errors
3. Try a different ROM or browser
4. Report issues on GitHub

### Contributing

PHPBoy is open source! Contributions are welcome:

- Report bugs on GitHub
- Submit pull requests
- Improve documentation
- Test on different browsers/devices

## Future Features

Planned improvements:

- [ ] Save file persistence with localStorage
- [ ] Save states
- [ ] Improved audio quality
- [ ] Better mobile controls
- [ ] Game Boy Camera support
- [ ] Multiplayer via WebRTC
- [ ] Debugger tools

## Resources

- [PHPBoy GitHub](https://github.com/eddmann/phpboy)
- [Game Boy Programming Manual](https://ia801906.us.archive.org/19/items/GameBoyProgManVer1.1/GameBoyProgManVer1.1.pdf)
- [Pan Docs](https://gbdev.io/pandocs/)
- [Game Boy Development Community](https://gbdev.io/)
