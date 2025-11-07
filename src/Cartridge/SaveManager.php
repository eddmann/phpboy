<?php

declare(strict_types=1);

namespace Gb\Cartridge;

use RuntimeException;

/**
 * Save Manager for Battery-Backed RAM and RTC
 *
 * Handles saving and loading of battery-backed RAM (.sav files)
 * and RTC state (.rtc files) for cartridges with these features.
 *
 * File Formats:
 * - .sav: Raw binary dump of RAM
 * - .rtc: JSON-encoded RTC state with timestamp
 */
final class SaveManager
{
    /**
     * Save RAM to a file.
     *
     * @param string $path Path to save file (.sav)
     * @param array<int, int> $ram RAM data
     * @throws RuntimeException If save fails
     */
    public function saveRam(string $path, array $ram): void
    {
        if (empty($ram)) {
            return; // Nothing to save
        }

        // Convert array to binary string
        $binary = '';
        foreach ($ram as $byte) {
            $binary .= chr($byte & 0xFF);
        }

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException("Failed to create directory: {$dir}");
            }
        }

        // Write to file
        if (file_put_contents($path, $binary) === false) {
            throw new RuntimeException("Failed to save RAM to: {$path}");
        }
    }

    /**
     * Load RAM from a file.
     *
     * @param string $path Path to save file (.sav)
     * @param int $expectedSize Expected RAM size in bytes
     * @return array<int, int> RAM data
     * @throws RuntimeException If load fails
     */
    public function loadRam(string $path, int $expectedSize): array
    {
        if (!file_exists($path)) {
            // No save file exists, return empty RAM
            return array_fill(0, $expectedSize, 0x00);
        }

        $binary = file_get_contents($path);
        if ($binary === false) {
            throw new RuntimeException("Failed to load RAM from: {$path}");
        }

        // Convert binary string to array
        $ram = [];
        $length = strlen($binary);
        for ($i = 0; $i < $length; $i++) {
            $ram[] = ord($binary[$i]);
        }

        // Pad or truncate to expected size
        if (count($ram) < $expectedSize) {
            $ram = array_pad($ram, $expectedSize, 0x00);
        } elseif (count($ram) > $expectedSize) {
            $ram = array_slice($ram, 0, $expectedSize);
        }

        return $ram;
    }

    /**
     * Save RTC state to a file.
     *
     * @param string $path Path to RTC file (.rtc)
     * @param array<string, int> $rtcState RTC state
     * @throws RuntimeException If save fails
     */
    public function saveRtc(string $path, array $rtcState): void
    {
        // Add timestamp for calculating elapsed time on load
        $data = [
            'timestamp' => time(),
            'rtc' => $rtcState,
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new RuntimeException("Failed to encode RTC state");
        }

        // Ensure directory exists
        $dir = dirname($path);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new RuntimeException("Failed to create directory: {$dir}");
            }
        }

        // Write to file
        if (file_put_contents($path, $json) === false) {
            throw new RuntimeException("Failed to save RTC to: {$path}");
        }
    }

    /**
     * Load RTC state from a file and adjust for elapsed time.
     *
     * @param string $path Path to RTC file (.rtc)
     * @return array<string, int>|null RTC state, or null if file doesn't exist
     * @throws RuntimeException If load fails
     */
    public function loadRtc(string $path): ?array
    {
        if (!file_exists($path)) {
            // No RTC file exists, return null (RTC will start at 0)
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            throw new RuntimeException("Failed to load RTC from: {$path}");
        }

        $data = json_decode($json, true);
        if (!is_array($data) || !isset($data['timestamp'], $data['rtc'])) {
            throw new RuntimeException("Invalid RTC file format: {$path}");
        }

        $timestamp = $data['timestamp'];
        $rtcState = $data['rtc'];

        // Calculate elapsed time since save
        $elapsedSeconds = time() - $timestamp;

        // Add elapsed time to RTC (if not halted)
        if (($rtcState['halt'] ?? 0) === 0) {
            $rtcState = $this->addSecondsToRtc($rtcState, $elapsedSeconds);
        }

        return $rtcState;
    }

    /**
     * Add seconds to RTC state.
     *
     * @param array<string, int> $rtcState RTC state
     * @param int $seconds Number of seconds to add
     * @return array<string, int> Updated RTC state
     */
    private function addSecondsToRtc(array $rtcState, int $seconds): array
    {
        $totalSeconds = $rtcState['seconds'] + $seconds;
        $totalMinutes = $rtcState['minutes'] + (int)($totalSeconds / 60);
        $totalHours = $rtcState['hours'] + (int)($totalMinutes / 60);
        $totalDays = $rtcState['days'] + (int)($totalHours / 24);

        $rtcState['seconds'] = $totalSeconds % 60;
        $rtcState['minutes'] = $totalMinutes % 60;
        $rtcState['hours'] = $totalHours % 24;

        // Handle day counter overflow (512 days max)
        if ($totalDays >= 512) {
            $rtcState['days'] = $totalDays % 512;
            $rtcState['dayHigh'] = ($rtcState['dayHigh'] ?? 0) | 0x80; // Set carry flag
        } else {
            $rtcState['days'] = $totalDays;
        }

        return $rtcState;
    }

    /**
     * Get save file path for a ROM.
     *
     * @param string $romPath Path to ROM file
     * @return string Path to save file (.sav)
     */
    public function getSavePath(string $romPath): string
    {
        return preg_replace('/\.(gb|gbc)$/i', '.sav', $romPath);
    }

    /**
     * Get RTC file path for a ROM.
     *
     * @param string $romPath Path to ROM file
     * @return string Path to RTC file (.rtc)
     */
    public function getRtcPath(string $romPath): string
    {
        return preg_replace('/\.(gb|gbc)$/i', '.rtc', $romPath);
    }

    /**
     * Check if a save file exists.
     *
     * @param string $path Path to save file
     * @return bool True if file exists
     */
    public function saveExists(string $path): bool
    {
        return file_exists($path);
    }

    /**
     * Delete a save file.
     *
     * @param string $path Path to save file
     * @return bool True if deleted successfully
     */
    public function deleteSave(string $path): bool
    {
        if (!file_exists($path)) {
            return false;
        }

        return unlink($path);
    }
}
