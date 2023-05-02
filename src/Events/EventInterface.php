<?php declare(strict_types=1);

/**
 * @package     Localzet Server
 * @link        https://github.com/localzet/Server
 *
 * @author      Ivan Zorin <creator@localzet.com>
 * @copyright   Copyright (c) 2018-2023 Localzet Group
 * @license     https://www.gnu.org/licenses/agpl AGPL-3.0 license
 *
 *              This program is free software: you can redistribute it and/or modify
 *              it under the terms of the GNU Affero General Public License as
 *              published by the Free Software Foundation, either version 3 of the
 *              License, or (at your option) any later version.
 *
 *              This program is distributed in the hope that it will be useful,
 *              but WITHOUT ANY WARRANTY; without even the implied warranty of
 *              MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *              GNU Affero General Public License for more details.
 *
 *              You should have received a copy of the GNU Affero General Public License
 *              along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace localzet\Server\Events;

use Throwable;

interface EventInterface
{
    /**
     * Delay the execution of a callback.
     * @param float $delay
     * @param callable $func
     * @param array $args
     * @return int
     */
    public function delay(float $delay, callable $func, array $args = []): int;

    /**
     * Delete a delay timer.
     * @param int $timerId
     * @return bool
     */
    public function offDelay(int $timerId): bool;

    /**
     * Repeatedly execute a callback.
     * @param float $interval
     * @param callable $func
     * @param array $args
     * @return int
     */
    public function repeat(float $interval, callable $func, array $args = []): int;

    /**
     * Delete a repeat timer.
     * @param int $timerId
     * @return bool
     */
    public function offRepeat(int $timerId): bool;

    /**
     * Execute a callback when a stream resource becomes readable or is closed for reading.
     * @param resource $stream
     * @param callable $func
     * @return void
     */
    public function onReadable($stream, callable $func): void;

    /**
     * Cancel a callback of stream readable.
     * @param resource $stream
     * @return bool
     */
    public function offReadable($stream): bool;

    /**
     * Execute a callback when a stream resource becomes writable or is closed for writing.
     * @param resource $stream
     * @param callable $func
     * @return void
     */
    public function onWritable($stream, callable $func): void;

    /**
     * Cancel a callback of stream writable.
     * @param resource $stream
     * @return bool
     */
    public function offWritable($stream): bool;

    /**
     * Execute a callback when a signal is received.
     * @param int $signal
     * @param callable $func
     * @return void
     * @throws Throwable
     */
    public function onSignal(int $signal, callable $func): void;

    /**
     * Cancel a callback of signal.
     * @param int $signal
     * @return bool
     */
    public function offSignal(int $signal): bool;

    /**
     * Delete all timer.
     * @return void
     */
    public function deleteAllTimer(): void;

    /**
     * Run the event loop.
     * @return void
     * @throws Throwable
     */
    public function run(): void;

    /**
     * Stop event loop.
     * @return void
     */
    public function stop(): void;

    /**
     * Get Timer count.
     * @return int
     */
    public function getTimerCount(): int;

    /**
     * Set error handler
     * @param callable $errorHandler
     * @return void
     */
    public function setErrorHandler(callable $errorHandler): void;

    /**
     * Get error handler
     * @return ?callable(Throwable)
     */
    public function getErrorHandler(): ?callable;
}
