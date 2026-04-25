<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

class ResearcherModel
{
    private DBConnector $db;

    public function __construct()
    {
        $this->db = DBConnector::getInstance();
    }

    public function findByOrcid(string $orcid): array|false
    {
        return $this->db->fetchOne(
            'SELECT r.*, i.name AS institution_name
             FROM researchers r
             LEFT JOIN institutions i ON r.primary_institution_id = i.id
             WHERE r.orcid = ?',
            [$orcid]
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            'SELECT r.*, i.name AS institution_name
             FROM researchers r
             LEFT JOIN institutions i ON r.primary_institution_id = i.id
             WHERE r.id = ?',
            [$id]
        );
    }

    public function findByUserId(int $userId): array|false
    {
        return $this->db->fetchOne(
            'SELECT r.*, i.name AS institution_name
             FROM researchers r
             LEFT JOIN institutions i ON r.primary_institution_id = i.id
             WHERE r.user_id = ?',
            [$userId]
        );
    }

    /** Daftar peneliti berdasarkan skor dampak tertinggi (opsional filter bidang). */
    public function getTopByScore(int $limit = 20, string $field = 'all'): array
    {
        $join   = 'LEFT JOIN wizdam_scores ws
                   ON ws.entity_type = "researcher" AND ws.entity_id = r.id
                   AND ws.calculated_at = (
                       SELECT MAX(calculated_at) FROM wizdam_scores
                       WHERE entity_type = "researcher" AND entity_id = r.id
                   )';
        $where  = $field !== 'all' ? 'WHERE r.research_field = ?' : '';
        $params = $field !== 'all' ? [$field, $limit] : [$limit];

        return $this->db->fetchAll(
            "SELECT r.id, r.orcid, r.full_name, r.research_field,
                    i.name AS institution_name,
                    COALESCE(ws.total_impact_score, 0) AS total_impact_score,
                    ws.score_academic, ws.score_social, ws.score_policy, ws.score_practical
             FROM researchers r
             LEFT JOIN institutions i ON r.primary_institution_id = i.id
             $join
             $where
             ORDER BY total_impact_score DESC
             LIMIT ?",
            $params
        );
    }

    public function search(string $query, int $limit = 30): array
    {
        return $this->db->fetchAll(
            'SELECT r.id, r.orcid, r.full_name, r.research_field,
                    i.name AS institution_name
             FROM researchers r
             LEFT JOIN institutions i ON r.primary_institution_id = i.id
             WHERE r.full_name LIKE ?
             LIMIT ?',
            ['%' . $query . '%', $limit]
        );
    }

    public function upsert(array $data): string
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM researchers WHERE orcid = ?',
            [$data['orcid']]
        );

        if ($existing) {
            $sets   = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
            $params = array_merge(array_values($data), [$existing['id']]);
            $this->db->query("UPDATE researchers SET $sets, updated_at = NOW() WHERE id = ?", $params);
            return (string) $existing['id'];
        }

        return $this->db->insert('researchers', $data);
    }

    /** Kaitkan profil yang sudah ada dengan user yang login (klaim profil). */
    public function claimByUser(int $researcherId, int $userId): void
    {
        $this->db->query(
            'UPDATE researchers SET user_id = ?, updated_at = NOW() WHERE id = ? AND user_id IS NULL',
            [$userId, $researcherId]
        );
    }
}
