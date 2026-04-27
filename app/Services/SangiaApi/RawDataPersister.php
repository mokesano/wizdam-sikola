<?php

declare(strict_types=1);

namespace Wizdam\Services\SangiaApi;

use Wizdam\Database\DBConnector;

/**
 * Menyimpan raw_data dari response Sangia API ke tabel cache di DB.
 * Dipanggil setiap kali wizdam-apis fetch dari sumber eksternal
 * (data_source !== 'wizdam_sikola_db').
 */
class RawDataPersister
{
    private static function db(): DBConnector
    {
        return DBConnector::getInstance();
    }

    /**
     * Simpan raw_data profil ORCID + Scopus ke author_profiles_cache.
     */
    public static function saveAuthorProfile(string $orcid, array $rawData): void
    {
        $db = self::db();

        $existing = $db->fetchOne(
            'SELECT orcid FROM author_profiles_cache WHERE orcid = ?',
            [$orcid]
        );

        $data = [
            'person_data' => isset($rawData['orcid_person']) ? json_encode($rawData['orcid_person']) : null,
            'works_data'  => isset($rawData['orcid_works'])  ? json_encode($rawData['orcid_works'])  : null,
            'scopus_data' => isset($rawData['scopus'])       ? json_encode($rawData['scopus'])        : null,
            'fetched_at'  => $rawData['fetched_at'] ?? date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            $db->query(
                'UPDATE author_profiles_cache SET person_data=?, works_data=?, scopus_data=?, fetched_at=? WHERE orcid=?',
                [$data['person_data'], $data['works_data'], $data['scopus_data'], $data['fetched_at'], $orcid]
            );
        } else {
            $db->query(
                'INSERT INTO author_profiles_cache (orcid, person_data, works_data, scopus_data, fetched_at) VALUES (?,?,?,?,?)',
                [$orcid, $data['person_data'], $data['works_data'], $data['scopus_data'], $data['fetched_at']]
            );
        }
    }

    /**
     * Simpan raw_data sitasi DOI ke citations_cache.
     */
    public static function saveCitation(string $doi, array $rawData): void
    {
        $db = self::db();

        $existing = $db->fetchOne('SELECT doi FROM citations_cache WHERE doi = ?', [$doi]);

        $metadata  = isset($rawData['metadata'])  ? json_encode($rawData['metadata'])  : null;
        $citations = isset($rawData['citations']) ? json_encode($rawData['citations']) : null;
        $counts    = isset($rawData['counts'])    ? json_encode($rawData['counts'])    : null;
        $fetchedAt = $rawData['fetched_at'] ?? date('Y-m-d H:i:s');

        if ($existing) {
            $db->query(
                'UPDATE citations_cache SET metadata=?, citations=?, counts=?, fetched_at=? WHERE doi=?',
                [$metadata, $citations, $counts, $fetchedAt, $doi]
            );
        } else {
            $db->query(
                'INSERT INTO citations_cache (doi, metadata, citations, counts, fetched_at) VALUES (?,?,?,?,?)',
                [$doi, $metadata, $citations, $counts, $fetchedAt]
            );
        }
    }

    /**
     * Simpan raw_data metrik jurnal ke journal_profiles_cache.
     */
    public static function saveJournalMetrics(string $issn, array $rawData, string $source = 'scopus'): void
    {
        $db = self::db();

        $existing = $db->fetchOne('SELECT issn FROM journal_profiles_cache WHERE issn = ?', [$issn]);

        $field     = $source === 'sinta' ? 'sinta_data' : 'scopus_data';
        $encoded   = json_encode($rawData);
        $fetchedAt = $rawData['fetched_at'] ?? date('Y-m-d H:i:s');

        if ($existing) {
            $db->query(
                "UPDATE journal_profiles_cache SET {$field}=?, fetched_at=? WHERE issn=?",
                [$encoded, $fetchedAt, $issn]
            );
        } else {
            $db->query(
                "INSERT INTO journal_profiles_cache (issn, {$field}, fetched_at) VALUES (?,?,?)",
                [$issn, $encoded, $fetchedAt]
            );
        }
    }

    /**
     * Simpan riwayat analisis (SDG, impact, trend, recommendation) ke analysis_history.
     */
    public static function saveAnalysis(string $orcid, string $analysisType, array $result): void
    {
        self::db()->query(
            'INSERT INTO analysis_history (orcid, analysis_type, result, calculated_at) VALUES (?, ?, ?, NOW())',
            [$orcid, $analysisType, json_encode($result)]
        );
    }

    /**
     * Log setiap panggilan ke Sangia API untuk monitoring efisiensi.
     */
    public static function logApiCall(
        ?int   $userId,
        string $endpoint,
        array  $params,
        string $status,
        int    $durationMs,
        string $dataSource = ''
    ): void {
        try {
            self::db()->query(
                'INSERT INTO api_call_logs (user_id, endpoint, params, status, duration_ms, data_source, called_at) VALUES (?,?,?,?,?,?,NOW())',
                [$userId, $endpoint, json_encode($params), $status, $durationMs, $dataSource]
            );
        } catch (\Throwable) {
            // Log gagal tidak boleh menghentikan proses utama
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Loader: ambil data dari cache untuk supplied_data pattern
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Muat profil author dari cache untuk dikirim sebagai supplied_data.
     * Return null jika belum ada di cache.
     */
    public static function loadAuthorProfile(string $orcid): ?array
    {
        $row = self::db()->fetchOne(
            'SELECT person_data, works_data, scopus_data FROM author_profiles_cache WHERE orcid = ?',
            [$orcid]
        );

        if (!$row) return null;

        return [
            'supplied_person' => $row['person_data'] ? json_decode($row['person_data'], true) : null,
            'supplied_works'  => $row['works_data']  ? json_decode($row['works_data'],  true) : [],
            'supplied_scopus' => $row['scopus_data'] ? json_decode($row['scopus_data'], true) : null,
        ];
    }

    public static function loadCitation(string $doi): ?array
    {
        $row = self::db()->fetchOne(
            'SELECT metadata, citations, counts FROM citations_cache WHERE doi = ?',
            [$doi]
        );

        if (!$row) return null;

        return [
            'metadata'  => $row['metadata']  ? json_decode($row['metadata'],  true) : [],
            'citations' => $row['citations'] ? json_decode($row['citations'], true) : [],
            'counts'    => $row['counts']    ? json_decode($row['counts'],    true) : [],
        ];
    }
}
