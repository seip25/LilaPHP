<?php

namespace Cli;

use Core\Config;
use Core\Cache;

/**
 * Server-Side Concurrency Benchmark Command (`php cli.php benchmark`).
 *
 * Executes real OS-level concurrent HTTP load tests using `curl_multi_exec`,
 * writing live progress snapshots into Redis every ~500ms for real-time
 * dashboard synchronization. Calculates P50/P95/P99 latency percentiles.
 *
 * @package Cli
 */
class Benchmark extends Command
{
    private const REDIS_PREFIX = 'lilaphp:benchmark:';
    private const REDIS_STOP_PREFIX = 'lilaphp:benchmark:stop:';
    private const SNAPSHOT_INTERVAL = 0.5;
    private const RESULT_TTL = 300;

    /**
     * Executes the benchmark with parsed CLI arguments.
     *
     * @param array $args CLI arguments (--url, --concurrency, --duration, --id)
     * @return int Exit code
     */
    public function run(array $args): int
    {
        $params = $this->parseArgs($args);
        $rawUrl = $params['url'];
        $concurrency = $params['concurrency'];
        $duration = $params['duration'];
        $benchId = $params['id'];

        $baseUrl = $this->resolveBaseUrl();
        $displayBaseUrl = Config::$APP_URL;

        if (!str_starts_with($rawUrl, 'http://') && !str_starts_with($rawUrl, 'https://')) {
            $targetUrl = $baseUrl . (str_starts_with($rawUrl, '/') ? $rawUrl : '/' . $rawUrl);
            $displayUrl = $displayBaseUrl . (str_starts_with($rawUrl, '/') ? $rawUrl : '/' . $rawUrl);
        } else {
            $targetUrl = $rawUrl;
            $displayUrl = $rawUrl;
        }

        $this->banner("LilaPHP Server-Side Benchmark (curl_multi)");
        $this->info("Target URL   : {$displayUrl}");
        $this->info("Concurrency  : {$concurrency}");
        $this->info("Duration     : {$duration}s");
        $this->info("Benchmark ID : {$benchId}");
        echo PHP_EOL;

        $redis = Cache::getRedis();
        if ($redis) {
            $redis->del(self::REDIS_STOP_PREFIX . $benchId);
        }

        return $this->executeBenchmark($targetUrl, $displayUrl, $concurrency, $duration, $benchId);
    }

    /**
     * Resolves the base URL for benchmark requests based on execution context.
     *
     * @return string
     */
    private function resolveBaseUrl(): string
    {
        if (file_exists('/.dockerenv')) {
            return 'http://nginx';
        }

        return Config::$APP_URL;
    }

    /**
     * Parses CLI arguments into a structured parameter array.
     *
     * @param array $args Raw CLI arguments
     * @return array{url: string, concurrency: int, duration: int, id: string}
     */
    private function parseArgs(array $args): array
    {
        $params = [
            'url' => '/api',
            'concurrency' => 100,
            'duration' => 5,
            'id' => 'bench_' . bin2hex(random_bytes(6))
        ];

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--url=')) {
                $params['url'] = substr($arg, 6);
            } elseif (str_starts_with($arg, '--concurrency=')) {
                $params['concurrency'] = max(1, min(2000, (int) substr($arg, 14)));
            } elseif (str_starts_with($arg, '--duration=')) {
                $params['duration'] = max(1, min(120, (int) substr($arg, 11)));
            } elseif (str_starts_with($arg, '--id=')) {
                $params['id'] = substr($arg, 5);
            }
        }

        return $params;
    }

    /**
     * Runs the curl_multi benchmark loop with live Redis progress reporting.
     *
     * @param string $targetUrl Fully resolved target URL
     * @param int $concurrency Number of concurrent connections
     * @param int $duration Test duration in seconds
     * @param string $benchId Unique benchmark identifier
     * @return int Exit code
     */
    private function executeBenchmark(string $targetUrl, string $displayUrl, int $concurrency, int $duration, string $benchId): int
    {
        $mh = curl_multi_init();
        $handles = [];
        $latencies = [];
        $successCount = 0;
        $errorCount = 0;
        $totalRequests = 0;

        $startTime = microtime(true);
        $endTime = $startTime + $duration;
        $lastSnapshot = $startTime;

        $this->writeSnapshot($benchId, [
            'status' => 'running',
            'progress_pct' => 0,
            'elapsed_sec' => 0,
            'duration_sec' => $duration,
            'total_requests' => 0,
            'success_count' => 0,
            'error_count' => 0,
            'rps' => 0,
            'avg_ms' => 0,
            'min_ms' => 0,
            'max_ms' => 0,
            'p50_ms' => 0,
            'p95_ms' => 0,
            'p99_ms' => 0,
            'concurrency' => $concurrency,
            'target_url' => $displayUrl
        ]);

        for ($i = 0; $i < $concurrency; $i++) {
            $ch = $this->createHandle($targetUrl);
            curl_multi_add_handle($mh, $ch);
            $handles[(int) $ch] = ['handle' => $ch, 'start' => microtime(true)];
        }

        $stopped = false;

        do {
            curl_multi_exec($mh, $active);

            while ($info = curl_multi_info_read($mh)) {
                $ch = $info['handle'];
                $key = (int) $ch;

                if (isset($handles[$key])) {
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $elapsed = (microtime(true) - $handles[$key]['start']) * 1000;
                    $latencies[] = round($elapsed, 2);
                    $totalRequests++;

                    if ($httpCode >= 200 && $httpCode < 400) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }

                    curl_multi_remove_handle($mh, $ch);
                    curl_close($ch);
                    unset($handles[$key]);

                    if (!$stopped && microtime(true) < $endTime) {
                        $ch = $this->createHandle($targetUrl);
                        curl_multi_add_handle($mh, $ch);
                        $handles[(int) $ch] = ['handle' => $ch, 'start' => microtime(true)];
                    }
                }
            }

            $now = microtime(true);

            if ($now - $lastSnapshot >= self::SNAPSHOT_INTERVAL) {
                $elapsedSec = $now - $startTime;
                $snapshot = $this->buildSnapshot(
                    $benchId,
                    'running',
                    $elapsedSec,
                    $duration,
                    $totalRequests,
                    $successCount,
                    $errorCount,
                    $concurrency,
                    $displayUrl,
                    $latencies
                );
                $this->writeSnapshot($benchId, $snapshot);
                $this->printProgress($snapshot);
                $lastSnapshot = $now;

                if ($this->isStopRequested($benchId)) {
                    $stopped = true;
                    $this->warning("Stop signal received. Finishing in-flight requests...");
                }
            }

            if ($now >= $endTime && !$stopped) {
                $stopped = true;
            }

            if ($active && !$stopped) {
                curl_multi_select($mh, 0.005);
            }

        } while (!empty($handles) || ($active > 0 && !$stopped));

        foreach ($handles as $data) {
            curl_multi_remove_handle($mh, $data['handle']);
            curl_close($data['handle']);
        }
        curl_multi_close($mh);

        $finalElapsed = microtime(true) - $startTime;
        $finalSnapshot = $this->buildSnapshot(
            $benchId,
            'completed',
            $finalElapsed,
            $duration,
            $totalRequests,
            $successCount,
            $errorCount,
            $concurrency,
            $displayUrl,
            $latencies
        );
        $this->writeSnapshot($benchId, $finalSnapshot, self::RESULT_TTL);

        echo PHP_EOL;
        $this->printFinalResults($finalSnapshot);

        return 0;
    }

    /**
     * Creates a configured cURL handle for benchmark requests.
     *
     * @param string $url Target URL
     * @return \CurlHandle
     */
    private function createHandle(string $url): \CurlHandle
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Connection: keep-alive',
            'Accept: application/json',
            'X-Benchmark: lilaphp'
        ]);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_TCP_NODELAY, true);
        return $ch;
    }

    /**
     * Builds a benchmark progress snapshot with calculated percentiles.
     *
     * @param string $benchId Benchmark identifier
     * @param string $status Current status (running|completed)
     * @param float $elapsedSec Elapsed time in seconds
     * @param int $duration Total configured duration
     * @param int $totalRequests Total requests completed
     * @param int $successCount Successful request count
     * @param int $errorCount Failed request count
     * @param int $concurrency Configured concurrency level
     * @param string $targetUrl Target URL
     * @param array $latencies Array of all recorded latencies in ms
     * @return array
     */
    private function buildSnapshot(
        string $benchId,
        string $status,
        float $elapsedSec,
        int $duration,
        int $totalRequests,
        int $successCount,
        int $errorCount,
        int $concurrency,
        string $targetUrl,
        array $latencies
    ): array {
        $rps = $elapsedSec > 0 ? round($totalRequests / $elapsedSec, 1) : 0;
        $avgMs = $totalRequests > 0 ? round(array_sum($latencies) / count($latencies), 2) : 0;
        $minMs = !empty($latencies) ? round(min($latencies), 2) : 0;
        $maxMs = !empty($latencies) ? round(max($latencies), 2) : 0;

        $sorted = $latencies;
        sort($sorted);
        $p50 = $this->percentile($sorted, 50);
        $p95 = $this->percentile($sorted, 95);
        $p99 = $this->percentile($sorted, 99);

        return [
            'id' => $benchId,
            'status' => $status,
            'progress_pct' => min(100, round(($elapsedSec / max(1, $duration)) * 100, 1)),
            'elapsed_sec' => round($elapsedSec, 2),
            'duration_sec' => $duration,
            'total_requests' => $totalRequests,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'rps' => $rps,
            'avg_ms' => $avgMs,
            'min_ms' => $minMs,
            'max_ms' => $maxMs,
            'p50_ms' => $p50,
            'p95_ms' => $p95,
            'p99_ms' => $p99,
            'concurrency' => $concurrency,
            'target_url' => $targetUrl
        ];
    }

    /**
     * Calculates a specific percentile from a pre-sorted latency array.
     *
     * @param array $sorted Sorted array of latency values
     * @param int $pct Percentile to calculate (0-100)
     * @return float
     */
    private function percentile(array $sorted, int $pct): float
    {
        if (empty($sorted)) {
            return 0;
        }

        $index = ($pct / 100) * (count($sorted) - 1);
        $lower = (int) floor($index);
        $upper = (int) ceil($index);
        $fraction = $index - $lower;

        if ($lower === $upper || !isset($sorted[$upper])) {
            return round($sorted[$lower], 2);
        }

        return round($sorted[$lower] + ($sorted[$upper] - $sorted[$lower]) * $fraction, 2);
    }

    /**
     * Writes a benchmark snapshot to Redis with optional TTL.
     *
     * @param string $benchId Benchmark identifier
     * @param array $snapshot Snapshot data array
     * @param int $ttl Time to live in seconds (0 = no expiry)
     * @return void
     */
    private function writeSnapshot(string $benchId, array $snapshot, int $ttl = 60): void
    {
        try {
            $redis = Cache::getRedis();
            if ($redis) {
                $key = self::REDIS_PREFIX . $benchId;
                $redis->setex($key, $ttl, json_encode($snapshot, JSON_UNESCAPED_UNICODE));
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * Checks whether a stop signal has been set for this benchmark.
     *
     * @param string $benchId Benchmark identifier
     * @return bool
     */
    private function isStopRequested(string $benchId): bool
    {
        try {
            $redis = Cache::getRedis();
            if ($redis) {
                return (bool) $redis->get(self::REDIS_STOP_PREFIX . $benchId);
            }
        } catch (\Throwable $e) {
        }
        return false;
    }

    /**
     * Prints a single-line progress update to the terminal.
     *
     * @param array $snapshot Current benchmark snapshot
     * @return void
     */
    private function printProgress(array $snapshot): void
    {
        $pct = str_pad(number_format($snapshot['progress_pct'], 1), 5, ' ', STR_PAD_LEFT);
        $rps = str_pad(number_format($snapshot['rps'], 0), 8, ' ', STR_PAD_LEFT);
        $avg = str_pad(number_format($snapshot['avg_ms'], 1), 8, ' ', STR_PAD_LEFT);
        $total = str_pad(number_format($snapshot['total_requests']), 8, ' ', STR_PAD_LEFT);

        echo "\r\033[36m[{$pct}%]\033[0m  RPS: \033[32m{$rps}\033[0m  Avg: \033[33m{$avg} ms\033[0m  Total: {$total}";
    }

    /**
     * Prints the final benchmark results summary to the terminal.
     *
     * @param array $snapshot Final benchmark snapshot
     * @return void
     */
    private function printFinalResults(array $snapshot): void
    {
        echo PHP_EOL;
        $this->banner("Benchmark Results");
        echo PHP_EOL;

        echo "  \033[1;37mTarget URL     :\033[0m {$snapshot['target_url']}" . PHP_EOL;
        echo "  \033[1;37mConcurrency    :\033[0m {$snapshot['concurrency']}" . PHP_EOL;
        echo "  \033[1;37mDuration       :\033[0m {$snapshot['elapsed_sec']}s" . PHP_EOL;
        echo PHP_EOL;

        echo "  \033[1;32mTotal Requests :\033[0m " . number_format($snapshot['total_requests']) . PHP_EOL;
        echo "  \033[1;32mReq/Sec (RPS)  :\033[0m " . number_format($snapshot['rps'], 1) . PHP_EOL;
        echo "  \033[1;32mSuccess (2xx)  :\033[0m " . number_format($snapshot['success_count']) . PHP_EOL;
        echo "  \033[1;31mErrors         :\033[0m " . number_format($snapshot['error_count']) . PHP_EOL;
        echo PHP_EOL;

        echo "  \033[1;33mAvg Latency    :\033[0m {$snapshot['avg_ms']} ms" . PHP_EOL;
        echo "  \033[1;33mMin Latency    :\033[0m {$snapshot['min_ms']} ms" . PHP_EOL;
        echo "  \033[1;33mMax Latency    :\033[0m {$snapshot['max_ms']} ms" . PHP_EOL;
        echo "  \033[1;35mP50 Latency    :\033[0m {$snapshot['p50_ms']} ms" . PHP_EOL;
        echo "  \033[1;35mP95 Latency    :\033[0m {$snapshot['p95_ms']} ms" . PHP_EOL;
        echo "  \033[1;35mP99 Latency    :\033[0m {$snapshot['p99_ms']} ms" . PHP_EOL;
        echo PHP_EOL;

        $this->success("Benchmark completed. Results available for 5 minutes in Redis.");
    }
}
