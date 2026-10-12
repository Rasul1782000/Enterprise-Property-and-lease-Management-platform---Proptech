<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * DebugHelper — Static utility for debugging anywhere in the application.
 *
 * Usage:
 *   DebugHelper::log('User logged in', ['user_id' => $user->id]);
 *   DebugHelper::dump($complexVariable);
 *   DebugHelper::trace(); // Shows backtrace
 *   DebugHelper::memory(); // Current memory usage
 *   DebugHelper::time(function() { ... }); // Time a closure
 */
class DebugHelper
{
    private const CHANNEL = 'debug';
    private const MAX_DEPTH = 5;
    private const MAX_STRING_LENGTH = 1000;
    private const MAX_ARRAY_ITEMS = 50;

    /**
     * Log a debug message with optional context.
     */
    public static function log(string $message, mixed $context = null, string $level = 'debug'): void
    {
        $caller = self::getCaller(2);
        $fullMessage = "[{$caller}] {$message}";

        $contextData = null;
        if ($context !== null) {
            $contextData = self::sanitizeContext($context);
        }

        Log::channel(self::CHANNEL)->{$level}($fullMessage, $contextData ?? []);
    }

    /**
     * Dump variable(s) to the debug log with formatting.
     */
    public static function dump(mixed ...$vars): void
    {
        $caller = self::getCaller(2);

        foreach ($vars as $i => $var) {
            $label  = count($vars) > 1 ? "Var " . ($i + 1) : 'Dump';
            $type   = gettype($var);
            $output = self::formatVariable($var, 0);

            Log::channel(self::CHANNEL)->debug("[{$caller}] {$label} ({$type}):", [
                'value' => $output,
            ]);
        }
    }

    /**
     * Log the current backtrace.
     */
    public static function trace(int $limit = 10): void
    {
        $caller = self::getCaller(2);
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $limit + 2);
        // Remove the first entries (this method + getCaller)
        array_splice($trace, 0, 2);

        $formatted = [];
        foreach ($trace as $i => $frame) {
            $formatted[] = [
                'step'     => $i + 1,
                'file'     => $frame['file'] ?? 'unknown',
                'line'     => $frame['line'] ?? '?',
                'function' => ($frame['class'] ?? '') . ($frame['type'] ?? '') . ($frame['function'] ?? ''),
            ];
        }

        Log::channel(self::CHANNEL)->debug("[{$caller}] Backtrace (last {$limit} calls):", [
            'trace' => $formatted,
        ]);
    }

    /**
     * Log current memory usage.
     */
    public static function memory(string $label = ''): void
    {
        $caller  = self::getCaller(2);
        $current = memory_get_usage(true) / 1048576;
        $peak    = memory_get_peak_usage(true) / 1048576;
        $real    = memory_get_usage(false) / 1048576;

        $message = $label ? "[{$caller}] Memory ({$label})" : "[{$caller}] Memory";

        Log::channel(self::CHANNEL)->debug($message, [
            'current_mb'     => round($current, 2),
            'peak_mb'        => round($peak, 2),
            'real_usage_mb'  => round($real, 2),
        ]);
    }

    /**
     * Time a closure execution and log the result.
     */
    public static function time(callable $callback, string $label = ''): mixed
    {
        $caller = self::getCaller(2);
        $start  = hrtime(true);
        $memStart = memory_get_usage(true);

        try {
            $result = $callback();

            $durationMs = (hrtime(true) - $start) / 1e6;
            $memUsedMb = (memory_get_usage(true) - $memStart) / 1048576;

            $message = $label ? "[{$caller}] Timed: {$label}" : "[{$caller}] Timed";

            Log::channel(self::CHANNEL)->debug($message, [
                'duration_ms' => round($durationMs, 2),
                'memory_mb'   => round($memUsedMb, 2),
            ]);

            return $result;
        } catch (Throwable $e) {
            $durationMs = (hrtime(true) - $start) / 1e6;

            Log::channel(self::CHANNEL)->error("[{$caller}] Timed (FAILED): {$label}", [
                'duration_ms' => round($durationMs, 2),
                'exception'   => $e->getMessage(),
                'file'        => $e->getFile(),
                'line'        => $e->getLine(),
            ]);

            throw $e;
        }
    }

    /**
     * Log database query count and slow queries.
     */
    public static function db(string $label = ''): void
    {
        $queries = DB::getQueryLog();
        $caller  = self::getCaller(2);

        $totalTime = 0;
        $slowQueries = [];
        foreach ($queries as $q) {
            $time = $q['time'] ?? 0;
            $totalTime += $time;
            if ($time > 100) { // queries over 100ms
                $slowQueries[] = [
                    'query'      => mb_substr($q['query'], 0, 300),
                    'bindings'   => $q['bindings'],
                    'time_ms'    => $time,
                ];
            }
        }

        $message = $label ? "[{$caller}] DB Stats ({$label})" : "[{$caller}] DB Stats";

        Log::channel(self::CHANNEL)->debug($message, [
            'total_queries'  => count($queries),
            'total_time_ms'  => round($totalTime, 2),
            'slow_queries'   => $slowQueries,
            'slow_threshold' => '100ms',
        ]);
    }

    /**
     * Log all configuration values (filtering sensitive ones).
     */
    public static function config(): void
    {
        $caller = self::getCaller(2);

        $config = config()->all();
        $filtered = self::filterSensitiveConfig($config);

        Log::channel(self::CHANNEL)->debug("[{$caller}] Configuration:", [
            'config' => $filtered,
        ]);
    }

    /**
     * Log all current environment variables (filtering sensitive ones).
     */
    public static function env(): void
    {
        $caller = self::getCaller(2);

        $sensitiveKeys = ['KEY', 'PASSWORD', 'SECRET', 'TOKEN', 'CREDENTIAL', 'DB_PASS', 'REDIS_PASS', 'MAIL_PASS'];
        $filtered = [];

        foreach ($_ENV as $key => $value) {
            $isSensitive = false;
            foreach ($sensitiveKeys as $s) {
                if (str_contains(strtoupper($key), $s)) {
                    $isSensitive = true;
                    break;
                }
            }
            $filtered[$key] = $isSensitive ? '***REDACTED***' : $value;
        }

        Log::channel(self::CHANNEL)->debug("[{$caller}] Environment Variables:", [
            'env' => $filtered,
        ]);
    }

    /**
     * Log model state (attributes, changes, original).
     */
    public static function model(Model $model, string $action = 'state'): void
    {
        $caller = self::getCaller(2);

        Log::channel(self::CHANNEL)->debug("[{$caller}] Model {$action}: {$model->getMorphClass()}", [
            'id'         => $model->getKey(),
            'attributes' => self::sanitizeContext($model->getAttributes()),
            'original'   => self::sanitizeContext($model->getOriginal()),
            'changes'    => self::sanitizeContext($model->getChanges()),
            'relations'  => array_keys($model->getRelations()),
        ]);
    }

    // ─── Private Helpers ───────────────────────────────────────────────────

    private static function getCaller(int $depth = 1): string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $depth + 1);
        $frame = $trace[$depth] ?? end($trace);

        $file = $frame['file'] ?? 'unknown';
        $file = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $file);
        $line = $frame['line'] ?? '?';

        $class = $frame['class'] ?? '';
        $func  = $frame['function'] ?? '';

        $shortClass = $class ? class_basename($class) : '';

        return "{$shortClass}:{$func} ({$file}:{$line})";
    }

    private static function sanitizeContext(mixed $data, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return '... (max depth reached)';
        }

        if (is_array($data)) {
            if (count($data) > self::MAX_ARRAY_ITEMS) {
                $sliced = array_slice($data, 0, self::MAX_ARRAY_ITEMS, true);
                $result = [];
                foreach ($sliced as $k => $v) {
                    $result[$k] = self::sanitizeContext($v, $depth + 1);
                }
                $result['...'] = (count($data) - self::MAX_ARRAY_ITEMS) . ' more items';
                return $result;
            }

            $result = [];
            foreach ($data as $k => $v) {
                $lowerKey = strtolower((string) $k);
                $sensitive = ['password', 'token', 'secret', 'credit_card', 'cvv', 'authorization'];
                $isSensitive = false;
                foreach ($sensitive as $s) {
                    if (str_contains($lowerKey, $s)) {
                        $isSensitive = true;
                        break;
                    }
                }
                $result[$k] = $isSensitive ? '***REDACTED***' : self::sanitizeContext($v, $depth + 1);
            }
            return $result;
        }

        if (is_object($data)) {
            if ($data instanceof Model) {
                return [
                    'class'      => get_class($data),
                    'id'         => $data->getKey(),
                    'attributes' => self::sanitizeContext($data->getAttributes(), $depth + 1),
                ];
            }
            if ($data instanceof \DateTimeInterface) {
                return $data->toIso8601String();
            }
            if ($data instanceof \Stringable) {
                return (string) $data;
            }
            return 'Object(' . get_class($data) . ')';
        }

        if (is_string($data) && strlen($data) > self::MAX_STRING_LENGTH) {
            return mb_substr($data, 0, self::MAX_STRING_LENGTH) . '... (truncated)';
        }

        return $data;
    }

    private static function formatVariable(mixed $var, int $depth = 0): string|array
    {
        if ($depth > self::MAX_DEPTH) {
            return '... (max depth)';
        }

        if (is_null($var))    return 'null';
        if (is_bool($var))    return $var ? 'true' : 'false';
        if (is_int($var))    return (string) $var;
        if (is_float($var))  return (string) $var;
        if (is_string($var)) return strlen($var) > self::MAX_STRING_LENGTH ? mb_substr($var, 0, self::MAX_STRING_LENGTH) . '...' : $var;

        if (is_array($var)) {
            $items = [];
            $count = 0;
            foreach ($var as $k => $v) {
                if ($count >= self::MAX_ARRAY_ITEMS) {
                    $items['...'] = (count($var) - $count) . ' more';
                    break;
                }
                $items[$k] = self::formatVariable($v, $depth + 1);
                $count++;
            }
            return $items;
        }

        if (is_object($var)) {
            if ($var instanceof Model) {
                return [
                    'class'  => class_basename($var),
                    'id'     => $var->getKey(),
                    'attrs'  => self::formatVariable($var->getAttributes(), $depth + 1),
                ];
            }
            return 'Object(' . get_class($var) . ')';
        }

        return gettype($var);
    }

    private static function filterSensitiveConfig(array $config): array
    {
        $sensitiveKeys = ['password', 'secret', 'key', 'token', 'credential', 'database', 'redis', 'mail'];
        $filtered = [];

        foreach ($config as $section => $values) {
            $lowerSection = strtolower((string) $section);
            $isSensitive = false;
            foreach ($sensitiveKeys as $s) {
                if (str_contains($lowerSection, $s)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive && is_array($values)) {
                $filtered[$section] = [];
                foreach ($values as $k => $v) {
                    $lowerKey = strtolower((string) $k);
                    $keySensitive = false;
                    foreach ($sensitiveKeys as $s) {
                        if (str_contains($lowerKey, $s)) {
                            $keySensitive = true;
                            break;
                        }
                    }
                    $filtered[$section][$k] = $keySensitive ? '***REDACTED***' : (is_array($v) ? '{array}' : $v);
                }
            } else {
                $filtered[$section] = is_array($values) ? '{array}' : $values;
            }
        }

        return $filtered;
    }
}
