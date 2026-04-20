<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

/**
 * Merepresentasikan tabel `journals`.
 *
 * Kolom: id, title, issn, e_issn, publisher, sinta_rank, sinta_score,
 *        scopus_sjr, wos_jif, is_predatory, total_articles,
 *        created_at, updated_at
 */
class JournalModel
{
    private DBConnector $db;

    public function __construct()
    {
        $this->db = DBConnector::getInstance();
    }

    public function findByIssn(string $issn): array|false
    {
        // Normalisasi: hapus tanda hubung
        $issn = preg_replace('/[^0-9X]/', '', strtoupper($issn));
        return $this->db->fetchOne(
            'SELECT * FROM journals WHERE REPLACE(issn, "-", "") = ? OR REPLACE(e_issn, "-", "") = ?',
            [$issn, $issn]
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne('SELECT * FROM journals WHERE id = ?', [$id]);
    }

    /** Ambil artikel terbaru milik jurnal ini. */
    public function getRecentArticles(int $journalId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            'SELECT a.id, a.title, a.year, a.citations,
                    a.impact_score, a.doi, a.authors_snapshot
             FROM articles a
             WHERE a.journal_id = ?
             ORDER BY a.year DESC, a.citations DESC
             LIMIT ?',
            [$journalId, $limit]
        );
    }

    /** Ringkasan metrik indeksasi (Scopus, WoS, SINTA). */
    public function getIndexingMetrics(int $journalId): array
    {
        $journal = $this->findById($journalId);
        if (!$journal) {
            return [];
        }

        return [
            'sinta'  => ['rank' => $journal['sinta_rank'],  'score' => $journal['sinta_score']],
            'scopus' => ['sjr'  => $journal['scopus_sjr']],
            'wos'    => ['jif'  => $journal['wos_jif']],
        ];
    }

    public function search(string $query, int $limit = 30): array
    {
        return $this->db->fetchAll(
            'SELECT id, title, issn, sinta_rank, scopus_sjr, total_articles
             FROM journals
             WHERE title LIKE ? OR issn LIKE ?
             ORDER BY sinta_score DESC
             LIMIT ?',
            ['%' . $query . '%', '%' . $query . '%', $limit]
        );
    }
}
