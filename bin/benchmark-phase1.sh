#!/bin/bash
#
# Phase 1 Optimization Benchmark Script
# Compares performance before and after Phase 1 optimizations
#
set -e

ROM="${ROM:-third_party/roms/commercial/tetris.gb}"
FRAMES="${FRAMES:-6000}"

if [ ! -f "$ROM" ]; then
    echo "Error: ROM file not found: $ROM"
    echo "Set ROM environment variable or place tetris.gb in third_party/roms/commercial/"
    exit 1
fi

echo "════════════════════════════════════════════════════════════"
echo "PHPBoy Phase 1 Optimization Benchmark"
echo "════════════════════════════════════════════════════════════"
echo "ROM: $ROM"
echo "Frames: $FRAMES"
echo ""

# Test 1: Baseline (without optimizations - temporarily disable ColorPool)
echo "Test 1: Baseline Performance (ColorPool disabled)"
echo "────────────────────────────────────────────────────────────"
echo "Running..."

# Create temporary file with ColorPool disabled
TEMP_COLOR=$(mktemp)
cat > "$TEMP_COLOR" << 'EOF'
<?php
namespace Gb\Ppu;
final class ColorPool {
    public static function init(): void {}
    public static function get(int $r, int $g, int $b): Color {
        return new Color($r, $g, $b);
    }
    public static function getDmgShade(int $shade): Color {
        $gray = match ($shade) {
            0 => 0xFF, 1 => 0xAA, 2 => 0x55, 3 => 0x00, default => 0xFF,
        };
        return new Color($gray, $gray, $gray);
    }
    public static function getFromGbc15bit(int $rgb15): Color {
        $r = ($rgb15 & 0x001F);
        $g = ($rgb15 & 0x03E0) >> 5;
        $b = ($rgb15 & 0x7C00) >> 10;
        return new Color(
            (int) (($r * 255) / 31),
            (int) (($g * 255) / 31),
            (int) (($b * 255) / 31),
        );
    }
    public static function getStats(): array { return ['hits' => 0, 'misses' => 0, 'size' => 0, 'hit_rate' => 0]; }
    public static function clear(): void {}
    public static function getMemoryUsage(): int { return 0; }
}
EOF

# Backup and replace ColorPool
cp src/Ppu/ColorPool.php src/Ppu/ColorPool.php.backup
cp "$TEMP_COLOR" src/Ppu/ColorPool.php

# Run benchmark
BASELINE_OUTPUT=$(docker compose run --rm phpboy php \
    -d opcache.jit_buffer_size=256M \
    -d opcache.jit=1255 \
    -d memory_limit=256M \
    bin/phpboy.php "$ROM" --headless --frames="$FRAMES" --benchmark 2>&1 || true)

# Restore ColorPool
mv src/Ppu/ColorPool.php.backup src/Ppu/ColorPool.php
rm -f "$TEMP_COLOR"

# Extract FPS
BASELINE_FPS=$(echo "$BASELINE_OUTPUT" | grep -oP 'Average FPS: \K[\d.]+' || echo "0")

echo "$BASELINE_OUTPUT" | tail -n 10
echo ""
echo "✓ Baseline FPS: $BASELINE_FPS"
echo ""

# Test 2: Phase 1 Optimizations (ColorPool + lazy flags)
echo "Test 2: Phase 1 Optimized (ColorPool + Lazy Flags)"
echo "────────────────────────────────────────────────────────────"
echo "Running..."

OPTIMIZED_OUTPUT=$(docker compose run --rm phpboy php \
    -d opcache.jit_buffer_size=256M \
    -d opcache.jit=1255 \
    -d memory_limit=256M \
    bin/phpboy.php "$ROM" --headless --frames="$FRAMES" --benchmark 2>&1 || true)

# Extract FPS
OPTIMIZED_FPS=$(echo "$OPTIMIZED_OUTPUT" | grep -oP 'Average FPS: \K[\d.]+' || echo "0")

echo "$OPTIMIZED_OUTPUT" | tail -n 10
echo ""
echo "✓ Phase 1 FPS: $OPTIMIZED_FPS"
echo ""

# Calculate improvement
if [ "$BASELINE_FPS" != "0" ] && [ "$OPTIMIZED_FPS" != "0" ]; then
    IMPROVEMENT=$(echo "scale=2; (($OPTIMIZED_FPS - $BASELINE_FPS) / $BASELINE_FPS) * 100" | bc 2>/dev/null || echo "N/A")

    echo "════════════════════════════════════════════════════════════"
    echo "RESULTS"
    echo "════════════════════════════════════════════════════════════"
    echo "Baseline FPS:    $BASELINE_FPS"
    echo "Phase 1 FPS:     $OPTIMIZED_FPS"

    if [ "$IMPROVEMENT" != "N/A" ]; then
        echo "Improvement:     +$IMPROVEMENT%"
        echo ""

        # Compare with expected
        EXPECTED=20
        COMPARISON=$(echo "scale=0; $IMPROVEMENT" | bc 2>/dev/null || echo "0")

        if [ "$COMPARISON" -ge "$EXPECTED" ]; then
            echo "✅ SUCCESS: Achieved expected +20% gain or better"
        elif [ "$COMPARISON" -ge 15 ]; then
            echo "⚠️  PARTIAL: Close to expected +20% gain"
        else
            echo "❌ BELOW TARGET: Expected +20%, got +$IMPROVEMENT%"
            echo "   Check that all optimizations are applied correctly"
        fi
    fi
else
    echo "Error: Could not extract FPS values from benchmark output"
    exit 1
fi

echo ""
echo "════════════════════════════════════════════════════════════"
echo "Next Steps:"
echo "  - Test in browser: make build-wasm-optimized && make serve-wasm"
echo "  - Add MessagePack: See docs/phase1-optimizations-implemented.md"
echo "  - Target: +35-40% with complete Phase 1"
echo "════════════════════════════════════════════════════════════"
