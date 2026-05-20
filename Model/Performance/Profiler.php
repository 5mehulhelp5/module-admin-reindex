<?php

declare(strict_types=1);

namespace ETechFlow\AdminReindex\Model\Performance;

/**
 * Thin profiler-tagging helper for the AdminReindex module's hot paths.
 *
 * Wraps Tideways span calls so traces captured in production are filterable
 * by `ETechFlow_AR_*` instead of relying on Magento's class-name auto-trace.
 * No-op when Tideways isn't installed — Blackfire / New Relic auto-instrument
 * via class+method names and don't need explicit spans.
 *
 * Self-contained (no dependency on a shared ETechFlow module) so DI works
 * the same on any merchant's install regardless of which other ETechFlow
 * modules they have enabled.
 *
 * Usage:
 *
 *   $span = Profiler::start('ETechFlow_AR_MassReindex');
 *   try {
 *       // ... hot path body
 *   } finally {
 *       Profiler::stop($span);
 *   }
 */
final class Profiler
{
    private static ?bool $tidewaysAvailable = null;

    /**
     * @param string $name
     * @return object|null
     */
    public static function start(string $name): ?object
    {
        if (self::$tidewaysAvailable === null) {
            self::$tidewaysAvailable = class_exists('\\Tideways\\Profiler', false);
        }
        if (!self::$tidewaysAvailable) {
            return null;
        }
        try {
            return \Tideways\Profiler::createSpan($name);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param object|null $span
     */
    public static function stop(?object $span): void
    {
        if ($span === null) {
            return;
        }
        try {
            if (method_exists($span, 'stopTimer')) {
                $span->stopTimer();
            }
        } catch (\Throwable $e) {
            // Never let instrumentation surface to the admin.
        }
    }
}
