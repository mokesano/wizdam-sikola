#!/usr/bin/env php
<?php

/**
 * Wizdam Queue Worker
 *
 * Menjalankan background jobs dari tabel `jobs` (atau Redis).
 * Setiap job dipick, dieksekusi, dan statusnya diperbarui.
 *
 * Penggunaan:
 *   php bin/worker.php [--once] [--sleep=3] [--max-jobs=0] [--verbose]
 *
 * Options:
 *   --once        Jalankan satu job lalu keluar (cocok untuk cron)
 *   --sleep=N     Waktu tunggu (detik) jika queue kosong. Default: 3
 *   --max-jobs=N  Batas total job yang diproses sebelum restart. 0 = tanpa batas
 *   --verbose     Tampilkan log detail setiap job
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Wizdam\Database\DBConnector;

// ── Bootstrap ─────────────────────────────────────────────────────────────────
$dotenv = Dotenv::createImmutable(BASE_PATH);
$dotenv->safeLoad();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'Asia/Makassar');

// ── Parse CLI arguments ───────────────────────────────────────────────────────
$opts     = getopt('', ['once', 'sleep:', 'max-jobs:', 'verbose']);
$runOnce  = isset($opts['once']);
$sleep    = (int) ($opts['sleep']     ?? 3);
$maxJobs  = (int) ($opts['max-jobs']  ?? 0);
$verbose  = isset($opts['verbose']);

// ── Helpers ───────────────────────────────────────────────────────────────────
function log_msg(string $level, string $msg): void {
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] [{$level}] {$msg}" . PHP_EOL;
}

function handle_signal(int $sig): never {
    log_msg('INFO', "Signal {$sig} diterima — worker berhenti.");
    exit(0);
}

// Tangkap SIGTERM / SIGINT agar worker berhenti dengan bersih
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, 'handle_signal');
    pcntl_signal(SIGINT,  'handle_signal');
}

// ── Job registry ──────────────────────────────────────────────────────────────
// Map class name → actual class (namespace fix)
$jobClassMap = [
    'ImpactAnalysisJob'   => \Wizdam\Jobs\ImpactAnalysisJob::class,
    'ResearcherCrawlerJob' => \Wizdam\Jobs\ResearcherCrawlerJob::class,
];

// ── Main loop ─────────────────────────────────────────────────────────────────
log_msg('INFO', "Worker dimulai. runOnce={$runOnce}, sleep={$sleep}s, maxJobs={$maxJobs}");

$db        = DBConnector::getInstance();
$processed = 0;

while (true) {
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }

    // Ambil satu job pending dengan priority tertinggi
    $row = $db->fetchOne(
        "SELECT * FROM jobs
         WHERE status = 'pending' AND available_at <= NOW()
         ORDER BY priority ASC, created_at ASC
         LIMIT 1"
    );

    if (!$row) {
        if ($runOnce) {
            log_msg('INFO', 'Tidak ada job pending. Selesai (--once).');
            break;
        }
        if ($verbose) log_msg('DEBUG', "Queue kosong. Tunggu {$sleep}s…");
        sleep($sleep);
        continue;
    }

    $jobId   = $row['job_id'];
    $class   = $row['class'];
    $payload = json_decode($row['payload'] ?? '{}', true);

    log_msg('INFO', "Memproses job [{$jobId}] class={$class}");

    // Tandai sebagai processing
    $db->query(
        "UPDATE jobs SET status='processing', started_at=NOW(), attempts=attempts+1 WHERE job_id=?",
        [$jobId]
    );

    $startTime = microtime(true);
    $success   = false;
    $errorMsg  = '';

    try {
        // Resolve class
        $resolvedClass = $jobClassMap[$class] ?? $class;

        if (!class_exists($resolvedClass)) {
            throw new \RuntimeException("Class {$resolvedClass} tidak ditemukan.");
        }

        $job    = new $resolvedClass($payload);
        $result = $job->handle();

        $duration = round(microtime(true) - $startTime, 2);
        $db->query(
            "UPDATE jobs SET status='completed', completed_at=NOW(), result=?, progress=100 WHERE job_id=?",
            [json_encode($result ?? []), $jobId]
        );

        log_msg('INFO', "Job [{$jobId}] selesai dalam {$duration}s.");
        $success = true;
        $processed++;

    } catch (\Throwable $e) {
        $errorMsg = $e->getMessage();
        $duration = round(microtime(true) - $startTime, 2);

        // Retry jika belum melebihi max_attempts
        $maxAttempts = (int) ($row['max_attempts'] ?? 3);
        $attempts    = ((int) $row['attempts']) + 1;

        if ($attempts >= $maxAttempts) {
            $db->query(
                "UPDATE jobs SET status='failed', failed_at=NOW(), error=? WHERE job_id=?",
                [$errorMsg, $jobId]
            );
            log_msg('ERROR', "Job [{$jobId}] GAGAL setelah {$attempts} percobaan: {$errorMsg}");
        } else {
            // Jadwalkan retry dengan exponential backoff
            $retryDelay = pow(2, $attempts) * 10; // 20s, 40s, 80s …
            $db->query(
                "UPDATE jobs SET status='pending', available_at=DATE_ADD(NOW(), INTERVAL ? SECOND), error=? WHERE job_id=?",
                [$retryDelay, $errorMsg, $jobId]
            );
            log_msg('WARN', "Job [{$jobId}] gagal (percobaan {$attempts}/{$maxAttempts}). Retry dalam {$retryDelay}s.");
        }
    }

    if ($runOnce) break;

    if ($maxJobs > 0 && $processed >= $maxJobs) {
        log_msg('INFO', "Batas {$maxJobs} job tercapai. Worker restart direkomendasikan.");
        break;
    }
}

log_msg('INFO', "Worker selesai. Total diproses: {$processed} job.");
