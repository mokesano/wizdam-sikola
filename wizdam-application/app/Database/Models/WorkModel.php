<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

class WorkModel
{
    private DBConnector $db;

    public function __construct()
    {
        $this->db = DBConnector::getInstance();
    }

    public function findByDoi(string $doi): array|false
    {
        return $this->db->fetchOne('SELECT * FROM works WHERE doi = ?', [$doi]);
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne('SELECT * FROM works WHERE id = ?', [$id]);
    }

    /** Semua karya milik satu peneliti, diurutkan terbaru. */
    public function getByResearcher(int $researcherId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            'SELECT w.id, w.doi, w.title, w.publication_year, w.type,
                    j.title AS journal_title, wa.author_order
             FROM works w
             JOIN work_authors wa ON wa.work_id = w.id
             LEFT JOIN journals j ON w.journal_id = j.id
             WHERE wa.researcher_id = ?
             ORDER BY w.publication_year DESC
             LIMIT ?',
            [$researcherId, $limit]
        );
    }

    /** Tren publikasi per tahun untuk satu peneliti (grafik). */
    public function getYearlyTrendByResearcher(int $researcherId): array
    {
        return $this->db->fetchAll(
            'SELECT w.publication_year AS year, COUNT(*) AS total
             FROM works w
             JOIN work_authors wa ON wa.work_id = w.id
             WHERE wa.researcher_id = ? AND w.publication_year IS NOT NULL
             GROUP BY w.publication_year
             ORDER BY w.publication_year ASC',
            [$researcherId]
        );
    }

    /** Tren publikasi per tahun untuk satu institusi. */
    public function getYearlyTrendByInstitution(int $institutionId): array
    {
        return $this->db->fetchAll(
            'SELECT w.publication_year AS year, COUNT(*) AS total
             FROM works w
             JOIN work_authors wa ON wa.work_id = w.id
             WHERE wa.institution_id = ? AND w.publication_year IS NOT NULL
             GROUP BY w.publication_year
             ORDER BY w.publication_year ASC',
            [$institutionId]
        );
    }

    public function upsert(array $data): string
    {
        if (!empty($data['doi'])) {
            $existing = $this->db->fetchOne('SELECT id FROM works WHERE doi = ?', [$data['doi']]);
            if ($existing) {
                $sets   = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
                $params = array_merge(array_values($data), [$existing['id']]);
                $this->db->query("UPDATE works SET $sets, updated_at = NOW() WHERE id = ?", $params);
                return (string) $existing['id'];
            }
        }
        return $this->db->insert('works', $data);
    }

    public function attachAuthor(int $workId, int $researcherId, ?int $institutionId, int $order = 1): void
    {
        $this->db->query(
            'INSERT IGNORE INTO work_authors (work_id, researcher_id, institution_id, author_order)
             VALUES (?, ?, ?, ?)',
            [$workId, $researcherId, $institutionId, $order]
        );
    }

    public function attachSdgLabel(int $workId, int $sdgCode, float $confidence, string $classifiedBy = 'sangia_api'): void
    {
        $this->db->query(
            'INSERT INTO work_sdg_labels (work_id, sdg_code, confidence_score, classified_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE confidence_score = VALUES(confidence_score)',
            [$workId, $sdgCode, $confidence, $classifiedBy]
        );
    }

    public function getSdgLabels(int $workId): array
    {
        return $this->db->fetchAll(
            'SELECT sdg_code, confidence_score, classified_by
             FROM work_sdg_labels
             WHERE work_id = ?
             ORDER BY confidence_score DESC',
            [$workId]
        );
    }
}
