#!/usr/bin/env php
<?php
/**
 * Bundle all PHPBoy source files into a single file for WASM
 */

$srcDir = __DIR__ . '/../src';
$outputFile = __DIR__ . '/../web/phpboy-wasm-full.php';

// Find all PHP files recursively
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($srcDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$phpFiles = [];
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $realPath = $file->getRealPath();
        if ($realPath !== false) {
            $phpFiles[] = $realPath;
        }
    }
}

// Sort for consistent output
sort($phpFiles);

echo "Found " . count($phpFiles) . " PHP files\n";

// Start building the bundle
$bundle = "<?php\n";
$bundle .= "/**\n";
$bundle .= " * PHPBoy WASM Bundle - Auto-generated\n";
$bundle .= " * Contains all emulator source files\n";
$bundle .= " */\n\n";

// Can't put any code before namespace declarations in PHP
// Will suppress warnings in the initialization block instead

foreach ($phpFiles as $file) {
    $relativePath = str_replace($srcDir . '/', '', $file);
    echo "Adding: $relativePath\n";

    $content = file_get_contents($file);
    if ($content === false) {
        echo "Warning: Could not read file $file, skipping\n";
        continue;
    }

    // Remove <?php opening tag (only at the very beginning)
    $content = preg_replace('/^\s*<\?php\s*\n?/', '', $content, 1);
    if ($content === null) {
        echo "Warning: preg_replace failed for <?php tag in $file, skipping\n";
        continue;
    }

    // Remove declare(strict_types=1); (only at the beginning)
    $content = preg_replace('/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;\s*\n?/', '', $content, 1);
    if ($content === null) {
        echo "Warning: preg_replace failed for declare statement in $file, skipping\n";
        continue;
    }

    // Remove typed class constants (PHP 8.3 feature, not supported in PHP 8.2)
    // Convert: private const int FOO = 1; -> private const FOO = 1;
    $content = preg_replace('/\bconst\s+(int|string|float|bool|array)\s+/', 'const ', $content);
    if ($content === null) {
        echo "Warning: preg_replace failed for typed constants in $file, skipping\n";
        continue;
    }

    // Convert namespace declaration to bracketed syntax if it exists
    // From: namespace Foo\Bar;
    // To: namespace Foo\Bar {
    if (preg_match('/^\s*namespace\s+([^;{]+);/m', $content, $matches)) {
        $namespace = trim($matches[1]);
        $content = preg_replace('/^\s*namespace\s+[^;]+;\s*\n?/m', "namespace $namespace {\n", $content, 1);
        if ($content === null) {
            echo "Warning: preg_replace failed for namespace in $file, skipping\n";
            continue;
        }

        // Add closing brace at the end
        $content = trim($content) . "\n} // end namespace $namespace\n";
    }

    // Add a comment marker
    $bundle .= "// FILE: $relativePath\n";
    $bundle .= trim($content);
    $bundle .= "\n\n";
}

// Add the initialization code at the end
$bundle .= <<<'PHP'
// INITIALIZATION CODE
namespace {
    // Suppress deprecation warnings for cleaner output
    error_reporting(E_ALL & ~E_DEPRECATED);

    use Gb\Emulator;
    use Gb\Frontend\Wasm\WasmAudioSink;
    use Gb\Frontend\Wasm\WasmFramebuffer;
    use Gb\Frontend\Wasm\WasmInput;

    global $emulator;

    if (!isset($emulator)) {
        echo "Initializing PHPBoy emulator...\n";

    // Verify ROM exists
    if (!file_exists('/rom.gb')) {
        throw new \RuntimeException("ROM file not found at /rom.gb");
    }

    echo "ROM found: " . filesize('/rom.gb') . " bytes\n";

    // Create emulator instance
    $emulator = new Emulator();

    // Set up WASM I/O implementations
    $framebuffer = new WasmFramebuffer();
    $audioSink = new WasmAudioSink();
    $input = new WasmInput();

    $emulator->setFramebuffer($framebuffer);
    $emulator->setAudioSink($audioSink);
    $emulator->setInput($input);

    // Load ROM from virtual filesystem
    $emulator->loadRom('/rom.gb');

    echo "Emulator initialized successfully\n";
    }
} // end global namespace

PHP;

file_put_contents($outputFile, $bundle);

echo "\nBundle created: $outputFile\n";
$fileSize = filesize($outputFile);
if ($fileSize !== false) {
    echo "Size: " . number_format($fileSize) . " bytes\n";
}
