<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

/**
 * Merepresentasikan tabel `publications`.
 *
 * Kolom utama: id, doi, title, abstract, publication_year, journal_title,
 *              cited_by_count, wizdam_score, sdgs_goals, document_type, access_type
 */
class ArticleModel
{
    private DBConnector $db;

    public function __construct()
    {
        $this->db = DBConnector::getInstance();
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne(
            'SELECT p.*,
                    GROUP_CONCAT(r.full_name ORDER BY pa.author_order SEPARATOR ", ") AS authors_list
             FROM publications p
             LEFT JOIN publication_authors pa ON pa.publication_id = p.id
             LEFT JOIN researchers r ON pa.researcher_id = r.id
             WHERE p.id = ?
             GROUP BY p.id',
            [$id]
        );
    }

    public function findByDoi(string $doi): array|false
    {
        return $this->db->fetchOne(
            'SELECT p.*,
                    GROUP_CONCAT(r.full_name ORDER BY pa.author_order SEPARATOR ", ") AS authors_list
             FROM publications p
             LEFT JOIN publication_authors pa ON pa.publication_id = p.id
             LEFT JOIN researchers r ON pa.researcher_id = r.id
             WHERE p.doi = ?
             GROUP BY p.id',
            [$doi]
        );
    }

    /** Daftar artikel dengan skor tertinggi, support filter tahun & keyword. */
    public function getTopByScore(
        int    $limit  = 20,
        int    $offset = 0,
        string $search = '',
        ?int   $year   = null,
        string $type   = 'all'
    ): array {
        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = 'MATCH(p.title, p.abstract) AGAINST(? IN BOOLEAN MODE)';
            $params[]     = $search . '*';
        }

        if ($year !== null) {
            $conditions[] = 'p.publication_year = ?';
            $params[]     = $year;
        }

        if ($type !== 'all') {
            $conditions[] = 'p.document_type = ?';
            $params[]     = $type;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "SELECT p.id, p.doi, p.title, p.publication_year, p.journal_title,
                       p.cited_by_count, p.wizdam_score, p.sdgs_goals, p.document_type,
                       p.access_type,
                       GROUP_CONCAT(r.full_name ORDER BY pa.author_order SEPARATOR ', ') AS authors_list
                FROM publications p
                LEFT JOIN publication_authors pa ON pa.publication_id = p.id
                LEFT JOIN researchers r ON pa.researcher_id = r.id
                $where
                GROUP BY p.id
                ORDER BY p.wizdam_score DESC, p.cited_by_count DESC
                LIMIT ? OFFSET ?";

        $params[] = $limit;
        $params[] = $offset;

        return $this->db->fetchAll($sql, $params);
    }

    /** Total artikel untuk pagination, sama filter dengan getTopByScore. */
    public function countFiltered(
        string $search = '',
        ?int   $year   = null,
        string $type   = 'all'
    ): int {
        $conditions = [];
        $params     = [];

        if ($search !== '') {
            $conditions[] = 'MATCH(title, abstract) AGAINST(? IN BOOLEAN MODE)';
            $params[]     = $search . '*';
        }

        if ($year !== null) {
            $conditions[] = 'publication_year = ?';
            $params[]     = $year;
        }

        if ($type !== 'all') {
            $conditions[] = 'document_type = ?';
            $params[]     = $type;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $row = $this->db->fetchOne("SELECT COUNT(*) AS cnt FROM publications $where", $params);
        return (int) ($row['cnt'] ?? 0);
    }

    /** Artikel milik satu peneliti berdasarkan researcher_id. */
    public function getByResearcher(int $researcherId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT p.id, p.doi, p.title, p.publication_year, p.journal_title,
                    p.cited_by_count, p.wizdam_score, p.sdgs_goals
             FROM publications p
             JOIN publication_authors pa ON pa.publication_id = p.id
             WHERE pa.researcher_id = ?
             ORDER BY p.publication_year DESC, p.cited_by_count DESC
             LIMIT ?',
            [$researcherId, $limit]
        );
    }

    /** Data tren: jumlah artikel & rata-rata skor per tahun. */
    public function getTrendsByYear(int $fromYear, int $toYear): array
    {
        return $this->db->fetchAll(
            'SELECT publication_year AS year,
                    COUNT(*)           AS total_publications,
                    AVG(wizdam_score)  AS avg_wizdam_score,
                    SUM(cited_by_count) AS total_citations
             FROM publications
             WHERE publication_year BETWEEN ? AND ?
             GROUP BY publication_year
             ORDER BY publication_year ASC',
            [$fromYear, $toYear]
        );
    }

    /** Distribusi dokumen berdasarkan journal atau field (untuk chart). */
    public function getJournalDistribution(int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT journal_title, COUNT(*) AS total,
                    AVG(wizdam_score) AS avg_score, SUM(cited_by_count) AS total_citations
             FROM publications
             WHERE journal_title IS NOT NULL AND journal_title != ""
             GROUP BY journal_title
             ORDER BY total DESC
             LIMIT ?',
            [$limit]
        );
    }

    /** Insert atau update berdasarkan DOI. */
    public function upsert(array $data): string
    {
        if (!empty($data['doi'])) {
            $existing = $this->db->fetchOne(
                'SELECT id FROM publications WHERE doi = ?',
                [$data['doi']]
            );

            if ($existing) {
                $sets   = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
                $params = array_merge(array_values($data), [$existing['id']]);
                $this->db->query(
                    "UPDATE publications SET $sets, updated_at = NOW() WHERE id = ?",
                    $params
                );
                return (string) $existing['id'];
            }
        }

        return $this->db->insert('publications', array_merge($data, [
            'created_at' => date('Y-m-d H:i:s'),
        ]));
    }
}
