<?php

namespace Wizdam\App\Jobs;

/**
 * Queue Manager untuk mengelola job queue
 * Mendukung Redis atau database-based queue
 */
class QueueManager
{
    private \Delight\Db\TableGateway\TableGateway $jobsTable;
    private string $queueDriver;
    private ?\Redis $redis = null;
    
    public function __construct(\Delight\Db\PdoDatabase $db, string $queueDriver = 'database')
    {
        $this->jobsTable = new \Delight\Db\TableGateway\TableGateway('jobs', $db);
        $this->queueDriver = $queueDriver;
        
        if ($queueDriver === 'redis' && extension_loaded('redis')) {
            $this->redis = new \Redis();
            $this->redis->connect($_ENV['REDIS_HOST'] ?? '127.0.0.1', (int)($_ENV['REDIS_PORT'] ?? 6379));
        }
    }
    
    /**
     * Push job ke queue
     */
    public function push(JobAbstract $job, int $priority = 5): string
    {
        $jobId = $job->jobId;
        
        if ($this->queueDriver === 'redis' && $this->redis) {
            // Redis queue
            $this->redis->zAdd('queue:jobs', $priority, $job->serialize());
        } else {
            // Database queue
            $this->jobsTable->insert([
                'job_id' => $jobId,
                'class' => get_class($job),
                'payload' => $job->serialize(),
                'status' => 'pending',
                'priority' => $priority,
                'attempts' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'available_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return $jobId;
    }
    
    /**
     * Pop job dari queue untuk diproses
     */
    public function pop(): ?JobAbstract
    {
        if ($this->queueDriver === 'redis' && $this->redis) {
            // Redis queue
            $jobs = $this->redis->zRange('queue:jobs', 0, 0, true);
            
            if (!empty($jobs)) {
                $jobData = key($jobs);
                $this->redis->zRem('queue:jobs', $jobData);
                return JobAbstract::unserialize($jobData);
            }
        } else {
            // Database queue
            $row = $this->jobsTable->selectRow(
                ['status' => 'pending'],
                null,
                ['priority' => 'ASC', 'created_at' => 'ASC'],
                1
            );
            
            if ($row) {
                $this->jobsTable->update(
                    ['status' => 'processing'],
                    ['id' => $row['id']]
                );
                
                return JobAbstract::unserialize($row['payload']);
            }
        }
        
        return null;
    }
    
    /**
     * Update status job
     */
    public function updateStatus(string $jobId, string $status, array $result = null): void
    {
        if ($this->queueDriver === 'redis' && $this->redis) {
            // Redis: store result di hash
            $this->redis->hSet("job:{$jobId}", 'status', $status);
            if ($result) {
                $this->redis->hSet("job:{$jobId}", 'result', json_encode($result));
            }
        } else {
            // Database
            $data = ['status' => $status];
            
            if ($status === 'completed') {
                $data['completed_at'] = date('Y-m-d H:i:s');
            } elseif ($status === 'failed') {
                $data['failed_at'] = date('Y-m-d H:i:s');
            }
            
            if ($result) {
                $data['result'] = json_encode($result);
            }
            
            $this->jobsTable->update($data, ['job_id' => $jobId]);
        }
    }
    
    /**
     * Get status job
     */
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
                'result' => isset($data['result']) ? json_decode($data['result'], true) : null
            ];
        } else {
            // Database
            $row = $this->jobsTable->selectRow(['job_id' => $jobId]);
            
            if (!$row) {
                return null;
            }
            
            return [
                'job_id' => $row['job_id'],
                'status' => $row['status'],
                'progress' => $row['progress'] ?? 0,
                'result' => $row['result'] ? json_decode($row['result'], true) : null,
                'error' => $row['error'] ?? null,
                'created_at' => $row['created_at'],
                'completed_at' => $row['completed_at']
            ];
        }
    }
    
    /**
     * Get queue length
     */
    public function getQueueLength(): int
    {
        if ($this->queueDriver === 'redis' && $this->redis) {
            return $this->redis->zCard('queue:jobs');
        } else {
            $row = $this->jobsTable->fetchCount(['status' => 'pending']);
            return (int)($row['count'] ?? 0);
        }
    }
    
    /**
     * Clear completed jobs older than X days
     */
    public function clearOldJobs(int $daysOld = 7): int
    {
        if ($this->queueDriver === 'redis' && $this->redis) {
            // Redis cleanup lebih kompleks, skip untuk sekarang
            return 0;
        } else {
            $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
            return $this->jobsTable->delete([
                'status' => 'completed',
                'completed_at <' => $cutoffDate
            ]);
        }
    }
}
