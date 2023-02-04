<?php

/**
 * @package     Triangle Server (WebCore)
 * @link        https://github.com/localzet/WebCore
 * @link        https://github.com/Triangle-org/Server
 * 
 * @author      Ivan Zorin (localzet) <creator@localzet.com>
 * @copyright   Copyright (c) 2018-2022 Localzet Group
 * @license     https://www.localzet.com/license GNU GPLv3 License
 */

namespace localzet\Core;

use Exception;
use Throwable;
use Revolt\EventLoop;
use RuntimeException;
use Swoole\Coroutine\System;
use localzet\Core\Events\EventInterface;
use localzet\Core\Events\Revolt;
use localzet\Core\Events\Swoole;
use localzet\Core\Events\Swow;
use function function_exists;
use function is_callable;
use function pcntl_alarm;
use function pcntl_signal;
use function time;
use const PHP_INT_MAX;
use const SIGALRM;

/**
 * Таймер
 *
 * Например:
 * localzet\Core\Timer::add($time_interval, callback, array($arg1, $arg2..));
 */
class Timer
{
    /**
     * Задачи, основанные на сигнале
     * [
     *   run_time => [[$func, $args, $persistent, time_interval],[$func, $args, $persistent, time_interval],..]],
     *   run_time => [[$func, $args, $persistent, time_interval],[$func, $args, $persistent, time_interval],..]],
     * ]
     *
     * @var array
     */
    protected static array $tasks = [];

    /**
     * Событие
     *
     * @var ?EventInterface
     */
    protected static ?EventInterface $event = null;

    /**
     * ID Таймера
     *
     * @var int
     */
    protected static int $timerId = 0;

    /**
     * Статус таймера
     * [
     *   timer_id1 => bool,
     *   timer_id2 => bool,
     * ]
     *
     * @var array
     */
    protected static array $status = [];

    /**
     * Инициализация
     *
     * @param EventInterface|null $event
     * @return void
     */
    public static function init(EventInterface $event = null)
    {
        if ($event) {
            self::$event = $event;
            return;
        }
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGALRM, ['\localzet\Core\Timer', 'signalHandle'], false);
        }
    }

    /**
     * Обработчик сигнала
     *
     * @return void
     */
    public static function signalHandle()
    {
        if (!self::$event) {
            pcntl_alarm(1);
            self::tick();
        }
    }

    /**
     * Добавить таймер
     *
     * @param float $timeInterval
     * @param callable $func
     * @param mixed|array $args
     * @param bool $persistent
     * @return int
     */
    public static function add(float $timeInterval, callable $func, null|array $args = [], bool $persistent = true): int
    {
        if ($timeInterval < 0) {
            throw new RuntimeException('$timeInterval не может быть меньше 0');
        }

        if ($args === null) {
            $args = [];
        }

        if (self::$event) {
            return $persistent ? self::$event->repeat($timeInterval, $func, $args) : self::$event->delay($timeInterval, $func, $args);
        }

        if (!Server::getAllServers()) {
            return false;
        }

        if (!is_callable($func)) {
            Server::safeEcho(new Exception("Невозможно вызвать функцию"));
            return false;
        }

        if (empty(self::$tasks)) {
            pcntl_alarm(1);
        }

        $runTime = time() + $timeInterval;
        if (!isset(self::$tasks[$runTime])) {
            self::$tasks[$runTime] = [];
        }

        self::$timerId = self::$timerId == PHP_INT_MAX ? 1 : ++self::$timerId;
        self::$status[self::$timerId] = true;
        self::$tasks[$runTime][self::$timerId] = [$func, (array)$args, $persistent, $timeInterval];

        return self::$timerId;
    }

    /**
     * Coroutine sleep.
     *
     * @param float $delay
     * @return void
     */
    public static function sleep(float $delay)
    {
        switch (Server::$eventLoopClass) {
                // Fiber
            case Revolt::class:
                $suspension = EventLoop::getSuspension();
                static::add($delay, function () use ($suspension) {
                    $suspension->resume();
                }, null, false);
                $suspension->suspend();
                return;
                // Swoole
            case Swoole::class:
                System::sleep($delay);
                return;
                // Swow
            case Swow::class:
                usleep($delay * 1000 * 1000);
                return;
        }
        throw new RuntimeException('Timer::sleep() требует revolt/event-loop. Запусти команду "composer require revolt/event-loop" и перезагрузи WebCore');
    }

    /**
     * Тик
     *
     * @return void
     */
    public static function tick()
    {
        if (empty(self::$tasks)) {
            pcntl_alarm(0);
            return;
        }
        $timeNow = time();
        foreach (self::$tasks as $runTime => $taskData) {
            if ($timeNow >= $runTime) {
                foreach ($taskData as $index => $oneTask) {
                    $taskFunc = $oneTask[0];
                    $taskArgs = $oneTask[1];
                    $persistent = $oneTask[2];
                    $timeInterval = $oneTask[3];
                    try {
                        $taskFunc(...$taskArgs);
                    } catch (Throwable $e) {
                        Server::safeEcho($e);
                    }
                    if ($persistent && !empty(self::$status[$index])) {
                        $newRunTime = time() + $timeInterval;
                        if (!isset(self::$tasks[$newRunTime])) self::$tasks[$newRunTime] = [];
                        self::$tasks[$newRunTime][$index] = [$taskFunc, (array)$taskArgs, $persistent, $timeInterval];
                    }
                }
                unset(self::$tasks[$runTime]);
            }
        }
    }

    /**
     * Удалить таймер
     *
     * @param int $timerId
     * @return bool
     */
    public static function del(int $timerId): bool
    {
        if (self::$event) {
            return self::$event->offDelay($timerId);
        }
        foreach (self::$tasks as $runTime => $taskData) {
            if (array_key_exists($timerId, $taskData)) {
                unset(self::$tasks[$runTime][$timerId]);
            }
        }
        if (array_key_exists($timerId, self::$status)) {
            unset(self::$status[$timerId]);
        }
        return true;
    }

    /**
     * Удалить все таймеры
     *
     * @return void
     */
    public static function delAll()
    {
        self::$tasks = self::$status = [];
        if (function_exists('pcntl_alarm')) {
            pcntl_alarm(0);
        }
        if (self::$event) {
            self::$event->deleteAllTimer();
        }
    }
}
