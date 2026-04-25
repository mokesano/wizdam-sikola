<?php

declare(strict_types=1);

namespace Wizdam\Database\Models;

use Wizdam\Database\DBConnector;

class ExternalIdentifierModel
{
    private DBConnector $db;

    public function __construct()
    {
        $this->db = DBConnector::getInstance();
    }

    public function get(string $entityType, int $entityId, string $provider): array|false
    {
        return $this->db->fetchOne(
            'SELECT * FROM external_identifiers
             WHERE entity_type = ? AND entity_id = ? AND provider = ?',
            [$entityType, $entityId, $provider]
        );
    }

    public function getAll(string $entityType, int $entityId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM external_identifiers
             WHERE entity_type = ? AND entity_id = ?
             ORDER BY provider ASC',
            [$entityType, $entityId]
        );
    }

    /** Simpan atau perbarui identifier eksternal beserta cache metadata-nya. */
    public function upsert(
        string  $entityType,
        int     $entityId,
        string  $provider,
        string  $providerId,
        ?array  $metadata = null
    ): void {
        $this->db->query(
            'INSERT INTO external_identifiers
                 (entity_type, entity_id, provider, provider_id, metadata_json, fetched_at)
             VALUES (?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                 provider_id   = VALUES(provider_id),
                 metadata_json = VALUES(metadata_json),
                 fetched_at    = NOW(),
                 updated_at    = NOW()',
            [
                $entityType,
                $entityId,
                $provider,
                $providerId,
                $metadata !== null ? json_encode($metadata) : null,
            ]
        );
    }

    /** Ambil nilai tertentu dari metadata JSON yang ter-cache. */
    public function getMetadataValue(string $entityType, int $entityId, string $provider, string $key): mixed
    {
        $row = $this->get($entityType, $entityId, $provider);
        if (!$row || !$row['metadata_json']) {
            return null;
        }
        $meta = json_decode($row['metadata_json'], true);
        return $meta[$key] ?? null;
    }
}
