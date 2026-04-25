<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

/**
 * Merepresentasikan tabel `institutions`.
 *
 * Kolom: id, name, acronym, type, province, city,
 *        sinta_id, scopus_affil_id, total_researchers,
 *        total_publications, avg_impact_score, created_at, updated_at
 */
class InstitutionModel
{
    private DBConnector $db;

    public function __construct()
    {
        $this->db = DBConnector::getInstance();
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            'SELECT * FROM institutions WHERE id = ?',
            [$id]
        );
    }

    public function findBySintaId(string $sintaId): array|false
    {
        return $this->db->fetchOne(
            'SELECT * FROM institutions WHERE sinta_id = ?',
            [$sintaId]
        );
    }

    /** Ambil institusi beserta jumlah peneliti aktifnya. */
    public function findWithResearcherCount(int $id): array|false
    {
        return $this->db->fetchOne(
            'SELECT i.*,
                    COUNT(r.id)      AS researcher_count,
                    SUM(r.total_citations) AS total_citations,
                    AVG(r.impact_score)    AS avg_impact_score
             FROM institutions i
             LEFT JOIN researchers r ON r.affiliation_id = i.id
             WHERE i.id = ?
             GROUP BY i.id',
            [$id]
        );
    }

    /** Peneliti di suatu institusi diurutkan berdasarkan skor. */
    public function getResearchers(int $institutionId, int $limit = 50): array
    {
        return $this->db->fetchAll(
            'SELECT id, orcid, name, impact_score, h_index, total_citations
             FROM researchers
             WHERE affiliation_id = ?
             ORDER BY impact_score DESC
             LIMIT ?',
            [$institutionId, $limit]
        );
    }

    public function getAll(string $province = ''): array
    {
        if ($province) {
            return $this->db->fetchAll(
                'SELECT * FROM institutions WHERE province = ? ORDER BY avg_impact_score DESC',
                [$province]
            );
        }
        return $this->db->fetchAll('SELECT * FROM institutions ORDER BY avg_impact_score DESC');
    }
}
