<?php

namespace QrzLite\Tests\Support;

/**
 * What a piece of work cost: wall time, CPU time of the process and PHP memory.
 *
 * Memory is what PHP allocates (memory_get_usage()); memory that libraries allocate themselves, such as an
 * SQLite database, is not counted. Measuring needs PHP 8.2 (memory_reset_peak_usage()).
 */
final class Usage
{
    /**
     * @param   float  $wall      Seconds of wall time.
     * @param   float  $cpu       Seconds of CPU time, user and system.
     * @param   int    $memory    Bytes: peak memory during the work, above the memory used before it.
     * @param   int    $retained  Bytes still used after the work: what its result keeps.
     */
    public function __construct(
        public readonly float $wall,
        public readonly float $cpu,
        public readonly int $memory,
        public readonly int $retained
    ) {
    }

    /**
     * Runs the work and measures it.
     *
     * @template T
     *
     * @param   callable(): T  $work
     *
     * @return  array{0: T, 1: self}
     */
    public static function of(callable $work): array
    {
        gc_collect_cycles();

        if (\function_exists('memory_reset_peak_usage')) {
            memory_reset_peak_usage();
        }

        $memory = memory_get_usage();
        $cpu    = self::cpu();
        $wall   = hrtime(true);
        $result = $work();
        $usage  = new self(
            (hrtime(true) - $wall) / 1e9,
            self::cpu() - $cpu,
            memory_get_peak_usage() - $memory,
            memory_get_usage() - $memory
        );

        return [$result, $usage];
    }

    /**
     * CPU time of this process so far, in seconds.
     */
    public static function cpu(): float
    {
        $usage = getrusage() ?: [];

        return ($usage['ru_utime.tv_sec'] ?? 0) + ($usage['ru_utime.tv_usec'] ?? 0) / 1e6
            + ($usage['ru_stime.tv_sec'] ?? 0) + ($usage['ru_stime.tv_usec'] ?? 0) / 1e6;
    }

    /**
     * One line for a report, e.g. "20,111 URLs: CPU 1.20 s (60 µs/URL), memory 40.1 MB (2.0 KB/URL)".
     */
    public function describe(int $urls): string
    {
        $urls = max(1, $urls);

        return \sprintf(
            '%s URLs: wall %.2f s, CPU %.2f s (%d µs/URL), memory %.1f MB (%.1f KB/URL)',
            number_format($urls),
            $this->wall,
            $this->cpu,
            (int) round($this->cpu / $urls * 1e6),
            $this->memory / 1048576,
            $this->memory / $urls / 1024
        );
    }
}
