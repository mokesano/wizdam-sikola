<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

class JournalModel
{
    private DBConnector $db;

    public function __construct()
    {
        $this->db = DBConnector::getInstance();
    }

    public function findByIssn(string $issn): array|false
    {
        $issn = preg_replace('/[^0-9Xx]/', '', $issn);
        return $this->db->fetchOne(
            'SELECT * FROM journals
             WHERE REPLACE(issn_p, "-", "") = ?
                OR REPLACE(issn_e, "-", "") = ?',
            [strtoupper($issn), strtoupper($issn)]
        );
    }

    public function findById(int $id): array|false
    {
        return $this->db->fetchOne('SELECT * FROM journals WHERE id = ?', [$id]);
    }

    public function getRecentWorks(int $journalId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            'SELECT w.id, w.title, w.publication_year, w.doi, w.type
             FROM works w
             WHERE w.journal_id = ?
             ORDER BY w.publication_year DESC
             LIMIT ?',
            [$journalId, $limit]
        );
    }

    /** Ambil semua external identifiers (SINTA rank, Scopus SJR, dsb.) untuk jurnal ini. */
    public function getExternalIds(int $journalId): array
    {
        $rows = (new ExternalIdentifierModel())->getAll('journal', $journalId);
        $result = [];
        foreach ($rows as $row) {
            $result[$row['provider']] = [
                'provider_id'   => $row['provider_id'],
                'metadata_json' => $row['metadata_json'] ? json_decode($row['metadata_json'], true) : null,
                'fetched_at'    => $row['fetched_at'],
            ];
        }
        return $result;
    }

    public function search(string $query, int $limit = 30): array
    {
        return $this->db->fetchAll(
            'SELECT id, title, issn_p, issn_e, publisher_name
             FROM journals
             WHERE title LIKE ? OR issn_p LIKE ? OR issn_e LIKE ?
             LIMIT ?',
            ['%' . $query . '%', '%' . $query . '%', '%' . $query . '%', $limit]
        );
    }
}
