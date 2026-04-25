<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

class HarvestingModel
{
    private DBConnector $db;

    public function __construct()
    {
        $this->db = DBConnector::getInstance();
    }

    // ── Harvesting Sources ────────────────────────────────────

    public function getAllSources(string $status = 'active'): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM harvesting_sources WHERE status = ? ORDER BY last_harvested_at ASC',
            [$status]
        );
    }

    public function findSource(int $id): array|false
    {
        return $this->db->fetchOne('SELECT * FROM harvesting_sources WHERE id = ?', [$id]);
    }

    public function upsertSource(string $baseUrl, ?string $setSpec, string $protocol = 'oai_pmh'): string
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM harvesting_sources WHERE base_url = ? AND (set_spec = ? OR (set_spec IS NULL AND ? IS NULL))',
            [$baseUrl, $setSpec, $setSpec]
        );

        if ($existing) {
            return (string) $existing['id'];
        }

        return $this->db->insert('harvesting_sources', [
            'base_url' => $baseUrl,
            'set_spec' => $setSpec,
            'protocol' => $protocol,
        ]);
    }

    public function updateSourceAfterHarvest(int $sourceId, ?string $resumptionToken, string $status = 'active'): void
    {
        $this->db->query(
            'UPDATE harvesting_sources
             SET last_harvested_at = NOW(), last_resumption_token = ?, status = ?
             WHERE id = ?',
            [$resumptionToken, $status, $sourceId]
        );
    }

    // ── Harvest Runs ──────────────────────────────────────────

    public function startRun(int $sourceId): string
    {
        return $this->db->insert('harvest_runs', [
            'source_id'  => $sourceId,
            'started_at' => date('Y-m-d H:i:s'),
            'status'     => 'running',
        ]);
    }

    public function finishRun(int $runId, int $added, int $updated, string $status = 'completed', ?string $error = null): void
    {
        $this->db->query(
            'UPDATE harvest_runs
             SET finished_at = NOW(), records_added = ?, records_updated = ?,
                 status = ?, error_message = ?
             WHERE id = ?',
            [$added, $updated, $status, $error, $runId]
        );
    }

    /** Ambil riwayat run 7 hari terakhir untuk grafik admin. */
    public function getRecentRuns(int $days = 7): array
    {
        return $this->db->fetchAll(
            'SELECT DATE(started_at) AS harvest_date,
                    COUNT(*)         AS total_runs,
                    SUM(records_added) AS total_added
             FROM harvest_runs
             WHERE started_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY harvest_date
             ORDER BY harvest_date ASC',
            [$days]
        );
    }
}
