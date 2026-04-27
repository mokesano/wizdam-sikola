<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

/**
 * Tabel `researchers` — field sesuai database_schema_full.sql:
 *   id, orcid_id, full_name, institution_id, sinta_id, scopus_id,
 *   h_index, total_citations, wizdam_score, position_title,
 *   research_field, biography, created_at, updated_at
 */
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
             LEFT JOIN institutions i ON r.institution_id = i.id
             WHERE r.orcid_id = ?',
            [$orcid]
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            'SELECT r.*, i.name AS institution_name
             FROM researchers r
             LEFT JOIN institutions i ON r.institution_id = i.id
             WHERE r.id = ?',
            [$id]
        );
    }

    /** Ambil daftar peneliti dengan wizdam_score tertinggi. */
    public function getTopByImpact(int $limit = 20, string $field = 'all'): array
    {
        $sql    = 'SELECT r.id, r.orcid_id, r.full_name, r.sinta_id, r.h_index,
                          r.total_citations, r.wizdam_score, r.position_title,
                          r.research_field, i.name AS institution_name
                   FROM researchers r
                   LEFT JOIN institutions i ON r.institution_id = i.id';
        $params = [];

        if ($field !== 'all') {
            $sql     .= ' WHERE r.research_field = ?';
            $params[] = $field;
        }

        $sql     .= ' ORDER BY r.wizdam_score DESC LIMIT ?';
        $params[] = $limit;

        return $this->db->fetchAll($sql, $params);
    }

    /** Cari peneliti berdasarkan nama (LIKE). */
    public function search(string $query, int $limit = 30): array
    {
        return $this->db->fetchAll(
            'SELECT r.id, r.orcid_id, r.full_name, r.wizdam_score, i.name AS institution_name
             FROM researchers r
             LEFT JOIN institutions i ON r.institution_id = i.id
             WHERE r.full_name LIKE ?
             ORDER BY r.wizdam_score DESC
             LIMIT ?',
            ['%' . $query . '%', $limit]
        );
    }

    /**
     * Upsert berdasarkan orcid_id.
     * Kolom dalam $data harus menggunakan nama field schema_full.
     */
    public function upsert(array $data): string
    {
        $existing = $this->db->fetchOne(
            'SELECT id FROM researchers WHERE orcid_id = ?',
            [$data['orcid_id']]
        );

        if ($existing) {
            $sets   = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
            $params = array_merge(array_values($data), [$existing['id']]);
            $this->db->query("UPDATE researchers SET $sets, updated_at = NOW() WHERE id = ?", $params);
            return (string) $existing['id'];
        }

        return $this->db->insert('researchers', array_merge($data, ['created_at' => date('Y-m-d H:i:s')]));
    }
}
