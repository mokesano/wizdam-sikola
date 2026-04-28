<?php

declare(strict_types=1);

namespace Wizdam\Jobs;

use PDO;
use Wizdam\Database\DBConnector;

/**
 * Queue Manager — mengelola job queue berbasis database atau Redis.
 */
class QueueManager
{
    private DBConnector $db;
    private string $queueDriver;
    private ?\Redis $redis = null;

    public function __construct(PDO $pdo, string $queueDriver = 'database')
    {
        $this->db          = DBConnector::getInstance();
        $this->queueDriver = $queueDriver;

        if ($queueDriver === 'redis' && extension_loaded('redis')) {
            $this->redis = new \Redis();
            $this->redis->connect(
                $_ENV['REDIS_HOST'] ?? '127.0.0.1',
                (int) ($_ENV['REDIS_PORT'] ?? 6379)
            );
        }
    }

    public function push(JobAbstract $job, int $priority = 5): string
    {
        $jobId = $job->jobId;

        if ($this->queueDriver === 'redis' && $this->redis) {
            $this->redis->zAdd('queue:jobs', $priority, $job->serialize());
        } else {
            $this->db->insert('jobs', [
                'job_id'       => $jobId,
                'class'        => get_class($job),
                'payload'      => $job->serialize(),
                'status'       => 'pending',
                'priority'     => $priority,
                'attempts'     => 0,
                'created_at'   => date('Y-m-d H:i:s'),
                'available_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $jobId;
    }

    public function pop(): ?JobAbstract
    {
        if ($this->queueDriver === 'redis' && $this->redis) {
            $jobs = $this->redis->zRange('queue:jobs', 0, 0, true);
            if (!empty($jobs)) {
                $payload = key($jobs);
                $this->redis->zRem('queue:jobs', $payload);
                return JobAbstract::unserialize($payload);
            }
            return null;
        }

        $row = $this->db->fetchOne(
            "SELECT * FROM jobs WHERE status = 'pending' ORDER BY priority ASC, created_at ASC LIMIT 1"
        );

        if (!$row) {
            return null;
        }

        $this->db->query(
            "UPDATE jobs SET status = 'processing' WHERE id = ?",
            [$row['id']]
        );

        return JobAbstract::unserialize($row['payload']);
    }

    public function updateStatus(string $jobId, string $status, ?array $result = null): void
    {
        if ($this->queueDriver === 'redis' && $this->redis) {
            $this->redis->hSet("job:{$jobId}", 'status', $status);
            if ($result !== null) {
                $this->redis->hSet("job:{$jobId}", 'result', json_encode($result));
            }
            return;
        }

        $data = ['status' => $status];
        if ($status === 'completed') {
            $data['completed_at'] = date('Y-m-d H:i:s');
        } elseif ($status === 'failed') {
            $data['failed_at'] = date('Y-m-d H:i:s');
        }
        if ($result !== null) {
            $data['result'] = json_encode($result);
        }

        $sets   = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
        $params = array_merge(array_values($data), [$jobId]);
        $this->db->query("UPDATE jobs SET $sets WHERE job_id = ?", $params);
    }

    public function getStatus(string $jobId): ?array
    {
        if ($this->queueDriver === 'redis' && $this->redis) {
            $data = $this->redis->hGetAll("job:{$jobId}");
            if (empty($data)) {
                return null;
            }
            return [
                'job_id' => $jobId,
                'status' => $data['status'] ?? 'unknown',
                'result' => isset($data['result']) ? json_decode($data['result'], true) : null,
            ];
        }

        $row = $this->db->fetchOne('SELECT * FROM jobs WHERE job_id = ?', [$jobId]);
        if (!$row) {
            return null;
        }

        return [
            'job_id'       => $row['job_id'],
            'status'       => $row['status'],
            'progress'     => $row['progress'] ?? 0,
            'result'       => $row['result'] ? json_decode($row['result'], true) : null,
            'error'        => $row['error'] ?? null,
            'created_at'   => $row['created_at'],
            'completed_at' => $row['completed_at'] ?? null,
        ];
    }

    public function getQueueLength(): int
    {
        if ($this->queueDriver === 'redis' && $this->redis) {
            return (int) $this->redis->zCard('queue:jobs');
        }

        $row = $this->db->fetchOne("SELECT COUNT(*) AS n FROM jobs WHERE status = 'pending'");
        return (int) ($row['n'] ?? 0);
    }

    public function clearOldJobs(int $daysOld = 7): int
    {
        if ($this->queueDriver === 'redis' && $this->redis) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
        $stmt   = $this->db->query(
            "DELETE FROM jobs WHERE status = 'completed' AND completed_at < ?",
            [$cutoff]
        );
        return $stmt->rowCount();
    }
}
