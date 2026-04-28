<?php

namespace Wizdam\Jobs;

/**
 * Base class untuk semua Job/Queue workers
 * Mendukung proses async untuk crawler dan analisis berat
 */
abstract class JobAbstract
{
    protected string $jobId;
    protected string $status = 'pending';
    protected array $data = [];
    protected ?string $error = null;
    protected ?string $result = null;
    protected int $progress = 0;
    protected \DateTimeInterface $createdAt;
    protected ?\DateTimeInterface $startedAt = null;
    protected ?\DateTimeInterface $completedAt = null;
    
    public function __construct(string $jobId, array $data = [])
    {
        $this->jobId = $jobId;
        $this->data = $data;
        $this->createdAt = new \DateTime();
    }
    
    /**
     * Method utama yang harus diimplementasikan oleh child class
     */
    abstract public function handle(): mixed;
    
    /**
     * Execute job dengan error handling
     */
    public function execute(): bool
    {
        try {
            $this->status = 'running';
            $this->startedAt = new \DateTime();
            
            $this->result = $this->handle();
            
            $this->status = 'completed';
            $this->progress = 100;
            $this->completedAt = new \DateTime();
            
            return true;
            
        } catch (\Throwable $e) {
            $this->status = 'failed';
            $this->error = $e->getMessage();
            $this->completedAt = new \DateTime();
            
            // Log error untuk debugging
            error_log("Job {$this->jobId} failed: " . $e->getMessage());
            
            return false;
        }
    }
    
    /**
     * Update progress job (0-100)
     */
    public function updateProgress(int $progress, string $message = ''): void
    {
        $this->progress = min(100, max(0, $progress));
        if ($message) {
            $this->data['progress_message'] = $message;
        }
    }
    
    /**
     * Get status job sebagai array
     */
    public function getStatus(): array
    {
        return [
            'job_id' => $this->jobId,
            'status' => $this->status,
            'progress' => $this->progress,
            'progress_message' => $this->data['progress_message'] ?? '',
            'error' => $this->error,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'started_at' => $this->startedAt?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s'),
            'result' => $this->result
        ];
    }
    
    /**
     * Serialize job untuk storage
     */
    public function serialize(): string
    {
        return json_encode([
            'job_id' => $this->jobId,
            'class' => static::class,
            'status' => $this->status,
            'data' => $this->data,
            'error' => $this->error,
            'result' => $this->result,
            'progress' => $this->progress,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'started_at' => $this->startedAt?->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Unserialize job dari storage
     */
    public static function unserialize(string $json): static
    {
        $data = json_decode($json, true);
        
        $job = new static($data['job_id'], $data['data']);
        $job->status = $data['status'];
        $job->error = $data['error'];
        $job->result = $data['result'];
        $job->progress = $data['progress'];
        $job->createdAt = new \DateTime($data['created_at']);
        $job->startedAt = $data['started_at'] ? new \DateTime($data['started_at']) : null;
        $job->completedAt = $data['completed_at'] ? new \DateTime($data['completed_at']) : null;
        
        return $job;
    }
}
