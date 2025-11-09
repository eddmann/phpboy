# Configuration Guide

## Overview

PHPBoy can be configured via INI configuration files. Configuration files control audio, video, input mappings, emulation settings, and debug options.

## Configuration File Locations

PHPBoy searches for configuration files in the following order (first found is used):

1. `./phpboy.ini` - Current directory
2. `~/.phpboy/config.ini` - User home directory
3. `~/.phpboy.ini` - User home directory (alternate)
4. `/etc/phpboy.ini` - System-wide (Linux/Unix)

## Configuration Format

Configuration files use INI format with sections:

```ini
[audio]
volume = 0.8
sample_rate = 48000
enabled = true

[video]
scale = 4
fullscreen = false

[input]
key_a = z
key_b = x
key_start = Enter
key_select = Shift
key_up = Up
key_down = Down
key_left = Left
key_right = Right

[emulation]
speed = 1.0
rewind_buffer = 60
autosave_interval = 60
pause_on_focus_loss = true

[debug]
show_fps = false
trace_enabled = false
```

## Configuration Sections

### [audio]

Audio playback and recording settings.

**volume** (float, 0.0-1.0, default: 0.8)
- Master volume level
- 0.0 = muted, 1.0 = maximum

**sample_rate** (integer, default: 48000)
- Audio sample rate in Hz
- Common values: 44100, 48000
- Higher = better quality, more CPU usage

**enabled** (boolean, default: true)
- Enable/disable audio output
- Set to `false` for headless operation

### [video]

Display and rendering settings.

**scale** (integer, 1-10, default: 4)
- Display scale factor
- Game Boy screen is 160×144, so scale=4 gives 640×576

**fullscreen** (boolean, default: false)
- Start in fullscreen mode
- Currently only affects browser frontend

### [input]

Keyboard button mappings.

Each key maps a Game Boy button to a keyboard key:

**key_a** (string, default: "z")
- Game Boy A button

**key_b** (string, default: "x")
- Game Boy B button

**key_start** (string, default: "Enter")
- Game Boy Start button

**key_select** (string, default: "Shift")
- Game Boy Select button

**key_up** (string, default: "Up")
- D-pad Up

**key_down** (string, default: "Down")
- D-pad Down

**key_left** (string, default: "Left")
- D-pad Left

**key_right** (string, default: "Right")
- D-pad Right

#### Supported Key Names

- Letter keys: `a`, `b`, `c`, ..., `z`
- Number keys: `0`, `1`, ..., `9`
- Arrow keys: `Up`, `Down`, `Left`, `Right`
- Special keys: `Space`, `Enter`, `Shift`, `Ctrl`, `Alt`, `Tab`, `Escape`
- Function keys: `F1`, `F2`, ..., `F12`

### [emulation]

Emulation behavior settings.

**speed** (float, 0.1-10.0, default: 1.0)
- Emulation speed multiplier
- 1.0 = normal speed (59.7 FPS)
- 2.0 = double speed (fast-forward)
- 0.5 = half speed (slow motion)

**rewind_buffer** (integer, 0-600, default: 60)
- Rewind buffer size in seconds
- How many seconds of gameplay can be rewound
- 0 = disable rewind
- Higher values use more memory (~200KB per second)

**autosave_interval** (integer, 0-3600, default: 60)
- Autosave interval in seconds
- How often to save battery-backed RAM
- 0 = disable autosave (save only on exit)

**pause_on_focus_loss** (boolean, default: true)
- Automatically pause when window loses focus
- Prevents audio glitches when tabbing away

### [debug]

Debug and development settings.

**show_fps** (boolean, default: false)
- Display FPS counter during gameplay

**trace_enabled** (boolean, default: false)
- Enable CPU instruction tracing
- Warning: Generates large log files

## Programmatic Usage

### Loading Configuration

```php
use Gb\Config\Config;

$config = new Config();

// Try default locations
if ($config->loadFromDefaultLocations()) {
    echo "Configuration loaded\n";
} else {
    echo "Using default configuration\n";
}

// Or load from specific file
$config->loadFromFile('/path/to/custom.ini');
```

### Reading Values

```php
// Get value with default
$volume = $config->get('audio', 'volume', 0.8);
$rewindBuffer = $config->get('emulation', 'rewind_buffer', 60);

// Get entire section
$audioSettings = $config->getSection('audio');
```

### Setting Values

```php
// Set individual value
$config->set('audio', 'volume', 0.5);

// Save to file
$config->saveToFile('~/.phpboy/config.ini');
```

## Example Configurations

### Performance Mode (Fast, No Frills)

```ini
[audio]
enabled = false

[video]
scale = 2

[emulation]
rewind_buffer = 0
autosave_interval = 0

[debug]
show_fps = true
```

### Quality Mode (Best Experience)

```ini
[audio]
volume = 1.0
sample_rate = 48000
enabled = true

[video]
scale = 4
fullscreen = false

[emulation]
speed = 1.0
rewind_buffer = 120
autosave_interval = 30
pause_on_focus_loss = true

[debug]
show_fps = true
```

### TAS Mode (Tool-Assisted Speedrun)

```ini
[audio]
enabled = false

[video]
scale = 4

[emulation]
speed = 1.0
rewind_buffer = 300
autosave_interval = 0

[debug]
show_fps = true
trace_enabled = true
```

### Headless Mode (Testing)

```ini
[audio]
enabled = false

[video]
scale = 1

[emulation]
speed = 10.0
rewind_buffer = 0
autosave_interval = 0
```

## Command-Line Override

Command-line options override configuration file settings:

```bash
# Config file says speed=1.0, but this sets speed=2.0
php bin/phpboy.php game.gb --speed=2.0

# Config file says audio=false, but this enables it
php bin/phpboy.php game.gb --audio
```

## Creating a Configuration File

### Quick Start

Create `~/.phpboy/config.ini`:

```bash
mkdir -p ~/.phpboy
cat > ~/.phpboy/config.ini <<EOF
[audio]
volume = 0.8
enabled = true

[video]
scale = 4

[emulation]
speed = 1.0
rewind_buffer = 60

[debug]
show_fps = false
EOF
```

### Save Current Settings

```php
// In your code:
$config->saveToFile(getenv('HOME') . '/.phpboy/config.ini');
```

## Troubleshooting

### Configuration Not Loading

Check that the file:
1. Exists in one of the search locations
2. Has valid INI syntax (no syntax errors)
3. Is readable by the current user

### Values Not Taking Effect

- Verify the section and key names are correct
- Check for typos (keys are case-sensitive)
- Ensure the value is the correct type (boolean/integer/float/string)
- Command-line options override config files

### Reset to Defaults

Delete or rename your config file:

```bash
mv ~/.phpboy/config.ini ~/.phpboy/config.ini.backup
```

PHPBoy will use built-in defaults.

## See Also

- [TAS Guide](tas-guide.md) - TAS configuration settings
- [Debugging Guide](debugging-guide.md) - Debug configuration options
- [User Guide](user-guide.md) - General usage information
