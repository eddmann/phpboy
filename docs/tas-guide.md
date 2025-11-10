# Tool-Assisted Speedrun (TAS) Guide

## Overview

PHPBoy includes TAS features for creating frame-perfect gameplay recordings. This is useful for:
- Creating speedruns with perfect execution
- Testing game mechanics
- Creating demonstration videos
- Debugging gameplay issues

## Features

- **Frame-by-frame input recording**: Capture every button press with frame precision
- **Deterministic playback**: Recordings replay exactly the same way every time
- **JSON format**: Human-readable and editable input files
- **Frame advance**: Step through gameplay one frame at a time
- **Lightweight storage**: Only records frames with input changes

## Recording Format

TAS recordings use JSON format:

```json
{
  "version": "1.0",
  "frames": 1000,
  "inputs": [
    {"frame": 0, "buttons": ["Start"]},
    {"frame": 10, "buttons": ["A"]},
    {"frame": 15, "buttons": ["A", "Right"]},
    {"frame": 20, "buttons": []}
  ]
}
```

### Fields

- **version**: Format version (currently "1.0")
- **frames**: Total number of frames recorded
- **inputs**: Array of input events
  - **frame**: Frame number (0-indexed)
  - **buttons**: Array of button names pressed on that frame

### Button Names

- `A`, `B`, `Start`, `Select`
- `Up`, `Down`, `Left`, `Right`

## Command-Line Usage

### Recording

```bash
# Record gameplay to a file
php bin/phpboy.php game.gb --record=recording.json

# Stop recording: Press Ctrl+C or close the emulator
```

### Playback

```bash
# Playback a recording
php bin/phpboy.php game.gb --playback=recording.json
```

### Options

- `--record=<path>`: Start recording inputs to specified file
- `--playback=<path>`: Playback inputs from specified file
- `--frame-advance`: Enable frame advance mode (press F to advance one frame)
- `--speed=<n>`: Playback speed multiplier

## Programmatic Usage

### Recording Inputs

```php
use Gb\Tas\InputRecorder;
use Gb\Input\Button;

$recorder = new InputRecorder();
$recorder->startRecording();

// Each frame during gameplay:
$pressedButtons = [Button::A, Button::Right];
$recorder->recordFrame($pressedButtons);

// When done:
$recorder->stopRecording();
$recorder->saveRecording('my-tas.json');
```

### Playing Back Inputs

```php
$recorder = new InputRecorder();
$recorder->loadRecording('my-tas.json');
$recorder->startPlayback();

// Each frame:
$buttons = $recorder->getPlaybackInputs();
// Feed $buttons to joypad

// Check if finished:
if ($recorder->isPlaybackFinished()) {
    echo "Playback complete!\n";
}
```

## Debugger TAS Commands

When running with `--debug`, additional TAS commands are available:

```
tas record <file>     - Start recording inputs
tas stop              - Stop recording
tas playback <file>   - Play back a recording
tas status            - Show recording/playback status
frame                 - Advance one frame (when paused)
```

## Tips for Creating TAS Recordings

### 1. Start with Savestates

Combine TAS with savestates for easier editing:

```bash
# Play normally to a specific point
php bin/phpboy.php game.gb

# In debugger: savestate start.state
# Then start recording from this point
```

### 2. Frame Advance for Precision

Use frame advance mode to execute inputs with perfect timing:

```bash
php bin/phpboy.php game.gb --debug --frame-advance
```

In debugger:
- `f` or `frame` - Advance one frame
- `run` - Resume normal execution

### 3. Manual Editing

Since recordings are JSON, you can edit them manually:

```json
{
  "version": "1.0",
  "frames": 100,
  "inputs": [
    {"frame": 0, "buttons": ["Start"]},
    {"frame": 30, "buttons": ["A"]},     // Jump at frame 30
    {"frame": 31, "buttons": []},        // Release all buttons
    {"frame": 60, "buttons": ["Right"]}  // Move right at frame 60
  ]
}
```

### 4. Determinism

For TAS to work correctly:
- Always use the same ROM file
- Start from the same savestate or initial state
- Don't rely on real-time clock or random events (unless seeded)

## Workflow Example

### Creating a Speedrun

1. **Plan the route**:
   ```bash
   php bin/phpboy.php game.gb --debug
   # Explore and plan your route
   ```

2. **Record segments**:
   ```bash
   # Segment 1: Start to first checkpoint
   php bin/phpboy.php game.gb --record=segment1.json

   # Save state at checkpoint
   # In debugger: savestate checkpoint1.state
   ```

3. **Combine and optimize**:
   - Edit JSON files to combine segments
   - Use frame advance to optimize tricky parts
   - Verify with playback

4. **Final verification**:
   ```bash
   php bin/phpboy.php game.gb --playback=final-run.json
   ```

## Known Limitations

- **Timing precision**: PHPBoy's timing may vary slightly from real hardware
- **RTC games**: Games with Real-Time Clock may not replay deterministically
- **External events**: Serial link, sensor input not supported in TAS
- **Performance**: Very long recordings (>10,000 frames) may use significant memory

## Advanced: Scripted TAS Creation

Generate TAS files programmatically:

```php
$tas = [
    'version' => '1.0',
    'frames' => 1000,
    'inputs' => [],
];

// Press A every 10 frames
for ($i = 0; $i < 1000; $i += 10) {
    $tas['inputs'][] = ['frame' => $i, 'buttons' => ['A']];
}

file_put_contents('auto-a-press.json', json_encode($tas, JSON_PRETTY_PRINT));
```

## Troubleshooting

### Playback Desync

If playback doesn't match your recording:
- Ensure using the exact same ROM
- Start from the same initial state (use savestates)
- Check that no external factors (RTC, random seeds) affect gameplay

### Recording Too Large

If JSON files become too large:
- Only record input changes (already done by default)
- Split into multiple segments
- Compress with gzip: `gzip recording.json`

## See Also

- [Savestate Format](savestate-format.md) - For combining TAS with savestates
- [Configuration](configuration.md) - TAS-related config options
- [Debugger Guide](debugging-guide.md) - Using debugger for TAS creation
