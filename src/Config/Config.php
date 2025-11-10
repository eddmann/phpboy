<?php

declare(strict_types=1);

namespace Gb\Config;

/**
 * Configuration Manager
 *
 * Loads and manages emulator configuration settings from INI files.
 *
 * Default config file locations (in order of priority):
 * 1. ./phpboy.ini (current directory)
 * 2. ~/.phpboy/config.ini (user home directory)
 * 3. /etc/phpboy.ini (system-wide)
 *
 * Configuration format (INI):
 * [audio]
 * volume = 0.8
 * sample_rate = 48000
 *
 * [video]
 * scale = 4
 * fullscreen = false
 *
 * [input]
 * key_a = z
 * key_b = x
 * key_start = Enter
 * key_select = Shift
 *
 * [emulation]
 * speed = 1.0
 * rewind_buffer = 60
 * autosave_interval = 60
 */
final class Config
{
    /** @var array<string, mixed> Configuration values */
    private array $config = [];

    /** @var array<string, mixed> Default configuration */
    private const DEFAULTS = [
        'audio' => [
            'volume' => 0.8,
            'sample_rate' => 48000,
            'enabled' => true,
        ],
        'video' => [
            'scale' => 4,
            'fullscreen' => false,
        ],
        'input' => [
            'key_a' => 'z',
            'key_b' => 'x',
            'key_start' => 'Enter',
            'key_select' => 'Shift',
            'key_up' => 'Up',
            'key_down' => 'Down',
            'key_left' => 'Left',
            'key_right' => 'Right',
        ],
        'emulation' => [
            'speed' => 1.0,
            'rewind_buffer' => 60,
            'autosave_interval' => 60,
            'pause_on_focus_loss' => true,
        ],
        'debug' => [
            'show_fps' => false,
            'trace_enabled' => false,
        ],
    ];

    public function __construct()
    {
        $this->config = self::DEFAULTS;
    }

    /**
     * Load configuration from a file.
     *
     * @param string $path Path to the INI file
     * @throws \RuntimeException If file cannot be read
     */
    public function loadFromFile(string $path): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }

        $ini = parse_ini_file($path, true);
        if ($ini === false) {
            throw new \RuntimeException("Failed to parse config file: {$path}");
        }

        // Merge with defaults
        foreach ($ini as $section => $values) {
            if (!is_array($values)) {
                continue;
            }
            if (!isset($this->config[$section])) {
                $this->config[$section] = [];
            }
            $this->config[$section] = array_merge($this->config[$section], $values);
        }
    }

    /**
     * Try to load configuration from default locations.
     *
     * Returns true if a config file was found and loaded.
     */
    public function loadFromDefaultLocations(): bool
    {
        $locations = $this->getDefaultConfigLocations();

        foreach ($locations as $path) {
            if (file_exists($path)) {
                $this->loadFromFile($path);
                return true;
            }
        }

        return false;
    }

    /**
     * Get default configuration file locations.
     *
     * @return array<string> Paths to check
     */
    private function getDefaultConfigLocations(): array
    {
        $locations = [];

        // Current directory
        $locations[] = getcwd() . '/phpboy.ini';

        // User home directory
        $home = getenv('HOME');
        if ($home) {
            $locations[] = $home . '/.phpboy/config.ini';
            $locations[] = $home . '/.phpboy.ini';
        }

        // System-wide (Linux/Unix)
        $locations[] = '/etc/phpboy.ini';

        return $locations;
    }

    /**
     * Get a configuration value.
     *
     * @param string $section Section name
     * @param string $key Key name
     * @param mixed $default Default value if not found
     * @return mixed Configuration value
     */
    public function get(string $section, string $key, mixed $default = null): mixed
    {
        return $this->config[$section][$key] ?? $default;
    }

    /**
     * Set a configuration value.
     *
     * @param string $section Section name
     * @param string $key Key name
     * @param mixed $value Value to set
     */
    public function set(string $section, string $key, mixed $value): void
    {
        if (!isset($this->config[$section])) {
            $this->config[$section] = [];
        }
        $this->config[$section][$key] = $value;
    }

    /**
     * Get all configuration values for a section.
     *
     * @param string $section Section name
     * @return array<string, mixed> Section values
     */
    public function getSection(string $section): array
    {
        $value = $this->config[$section] ?? [];
        return is_array($value) ? $value : [];
    }

    /**
     * Get all configuration values.
     *
     * @return array<string, mixed> All configuration
     */
    public function getAll(): array
    {
        return $this->config;
    }

    /**
     * Save configuration to a file.
     *
     * @param string $path Path to save the INI file
     * @throws \RuntimeException If file cannot be written
     */
    public function saveToFile(string $path): void
    {
        $ini = '';
        foreach ($this->config as $section => $values) {
            if (!is_array($values)) {
                continue;
            }
            $ini .= "[{$section}]\n";
            foreach ($values as $key => $value) {
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                $ini .= (string) $key . " = " . (string) $value . "\n";
            }
            $ini .= "\n";
        }

        if (file_put_contents($path, $ini) === false) {
            throw new \RuntimeException("Failed to save config file: {$path}");
        }
    }
}
