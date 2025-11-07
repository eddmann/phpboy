<?php

declare(strict_types=1);

namespace Gb\Input;

/**
 * Interface for input sources.
 *
 * Abstracts input polling to allow different frontends (CLI, browser, etc.)
 * to provide button state information to the emulator.
 */
interface InputInterface
{
    /**
     * Poll for currently pressed buttons.
     *
     * @return Button[] Array of currently pressed buttons
     */
    public function poll(): array;
}
