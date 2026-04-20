<?php

namespace Wizdam\App\Services;

/**
 * API Key Manager untuk mengelola API keys pengguna
 * Terintegrasi dengan database untuk CRUD operations
 */
class ApiKeyManager
{
    private \Delight\Db\TableGateway\TableGateway $table;
    
    public function __construct(\Delight\Db\PdoDatabase $db)
    {
        $this->table = new \Delight\Db\TableGateway\TableGateway('api_keys', $db);
    }
    
    /**
     * Generate API key baru untuk user
     */
    public function generateKey(int $userId, string $name, array $permissions = [], ?string $expiresAt = null): array
    {
        $apiKey = 'wzd_' . \Wizdam\Library\Helpers\Helpers::randomString(32);
        $secret = \Wizdam\Library\Helpers\Helpers::randomString(64);
        
        $data = [
            'user_id' => $userId,
            'name' => $name,
            'api_key' => $apiKey,
            'api_secret' => password_hash($secret, PASSWORD_DEFAULT),
            'permissions' => json_encode($permissions),
            'expires_at' => $expiresAt,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'last_used_at' => null
        ];
        
        $id = $this->table->insert($data);
        
        return [
            'id' => $id,
            'api_key' => $apiKey,
            'api_secret' => $secret, // Hanya ditampilkan sekali saat creation
            'name' => $name,
            'permissions' => $permissions,
            'expires_at' => $expiresAt
        ];
    }
    
    /**
     * Validasi API key
     */
    public function validateKey(string $apiKey, string $apiSecret): ?array
    {
        $row = $this->table->selectRow(['api_key' => $apiKey]);
        
        if (!$row) {
            return null;
        }
        
        if (!password_verify($apiSecret, $row['api_secret'])) {
            return null;
        }
        
        if (!$row['is_active']) {
            throw new \RuntimeException("API key telah dinonaktifkan");
        }
        
        if ($row['expires_at'] && strtotime($row['expires_at']) < time()) {
            throw new \RuntimeException("API key telah kadaluarsa");
        }
        
        // Update last_used_at
        $this->table->update(
            ['last_used_at' => date('Y-m-d H:i:s')],
            ['id' => $row['id']]
        );
        
        return [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'name' => $row['name'],
            'permissions' => json_decode($row['permissions'], true),
            'expires_at' => $row['expires_at']
        ];
    }
    
    /**
     * Dapatkan semua API key untuk user
     */
    public function getUserKeys(int $userId): array
    {
        return $this->table->selectRows(['user_id' => $userId]) ?? [];
    }
    
    /**
     * Revoke API key
     */
    public function revokeKey(int $keyId): bool
    {
        return $this->table->update(
            ['is_active' => false],
            ['id' => $keyId]
        ) > 0;
    }
    
    /**
     * Delete API key permanently
     */
    public function deleteKey(int $keyId): bool
    {
        return $this->table->delete(['id' => $keyId]) > 0;
    }
    
    /**
     * Update permissions API key
     */
    public function updatePermissions(int $keyId, array $permissions): bool
    {
        return $this->table->update(
            ['permissions' => json_encode($permissions)],
            ['id' => $keyId]
        ) > 0;
    }
    
    /**
     * Check permission untuk API key tertentu
     */
    public function hasPermission(int $keyId, string $permission): bool
    {
        $row = $this->table->selectRow(['id' => $keyId]);
        
        if (!$row) {
            return false;
        }
        
        $permissions = json_decode($row['permissions'], true);
        return in_array($permission, $permissions);
    }
}
