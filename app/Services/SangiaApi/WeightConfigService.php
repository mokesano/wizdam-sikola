<?php

declare(strict_types=1);

namespace Wizdam\Services\SangiaApi;

use Wizdam\Database\DBConnector;

/**
 * Memuat konfigurasi bobot analisis dari tabel `analysis_weight_configs`.
 * Admin panel Wizdam Sikola dapat mengubah bobot ini.
 * Nilai dalam kode hanya fallback jika DB belum dikonfigurasi.
 */
class WeightConfigService
{
    private static ?array $cache = null;

    private static function db(): DBConnector
    {
        return DBConnector::getInstance();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bobot SDG Scoring
    // ─────────────────────────────────────────────────────────────────────────

    /** Bobot SDG scoring untuk versi tertentu (default v5). */
    public static function forSdg(string $version = 'v5'): array
    {
        $key = "sdg_{$version}";
        $row = self::loadRaw($key);

        return $row ?? [
            'keyword'     => 0.30,
            'similarity'  => 0.30,
            'substantive' => 0.20,
            'causal'      => 0.20,
            'max_sdgs'    => 7,
            'thresholds'  => [
                'min'        => 0.20,
                'confidence' => 0.30,
                'high'       => 0.60,
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bobot Komposit Impact Score
    // ─────────────────────────────────────────────────────────────────────────

    /** Bobot komposit 4 pilar Wizdam Impact Score. */
    public static function forImpact(): array
    {
        $row = self::loadRaw('impact_composite');

        return $row ?? [
            'academic' => 0.40,
            'social'   => 0.25,
            'economic' => 0.20,
            'sdg'      => 0.15,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD — dipakai Admin Panel
    // ─────────────────────────────────────────────────────────────────────────

    public static function save(string $configKey, array $weights, ?int $updatedBy = null): void
    {
        $db  = self::db();
        $exists = $db->fetchOne(
            'SELECT id FROM analysis_weight_configs WHERE config_key = ?',
            [$configKey]
        );

        if ($exists) {
            $db->query(
                'UPDATE analysis_weight_configs SET weights = ?, updated_by = ?, updated_at = NOW() WHERE config_key = ?',
                [json_encode($weights), $updatedBy, $configKey]
            );
        } else {
            $db->query(
                'INSERT INTO analysis_weight_configs (config_key, weights, updated_by, updated_at) VALUES (?, ?, ?, NOW())',
                [$configKey, json_encode($weights), $updatedBy]
            );
        }

        self::$cache = null; // Invalidate cache
    }

    public static function all(): array
    {
        $rows = self::db()->fetchAll('SELECT config_key, weights, updated_at FROM analysis_weight_configs ORDER BY config_key');
        return array_map(fn($r) => [
            'config_key' => $r['config_key'],
            'weights'    => json_decode($r['weights'], true),
            'updated_at' => $r['updated_at'],
        ], $rows);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal
    // ─────────────────────────────────────────────────────────────────────────

    private static function loadRaw(string $key): ?array
    {
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        try {
            $row = self::db()->fetchOne(
                'SELECT weights FROM analysis_weight_configs WHERE config_key = ?',
                [$key]
            );

            if ($row) {
                $weights = json_decode($row['weights'], true);
                self::$cache[$key] = $weights;
                return $weights;
            }
        } catch (\Throwable) {
            // Tabel belum ada atau error DB — gunakan fallback default
        }

        return null;
    }
}
