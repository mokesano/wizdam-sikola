<?php

declare(strict_types=1);

namespace Wizdam\Services;

use Wizdam\Database\DatabaseManager;

/**
 * PageService - Mengelola halaman statis dan konten CMS
 * 
 * Service ini menangani pengambilan dan penyimpanan konten halaman
 * yang dapat dikelola oleh Admin melalui dashboard.
 */
class PageService
{
    private DatabaseManager $db;
    
    public function __construct(DatabaseManager $db)
    {
        $this->db = $db;
    }
    
    /**
     * Dapatkan konfigurasi situs global
     */
    public function getSiteConfig(): array
    {
        $stmt = $this->db->pdo()->prepare("SELECT config_key, config_value FROM site_config");
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $config = [];
        foreach ($rows as $row) {
            $config[$row['config_key']] = json_decode($row['config_value'], true) ?? $row['config_value'];
        }
        
        return $config;
    }
    
    /**
     * Dapatkan halaman berdasarkan slug
     */
    public function getPageBySlug(string $slug): ?array
    {
        $stmt = $this->db->pdo()->prepare("
            SELECT id, title, slug, content, meta_title, meta_description, 
                   meta_keywords, is_published, created_at, updated_at
            FROM pages
            WHERE slug = :slug AND is_published = 1
        ");
        $stmt->execute(['slug' => $slug]);
        $page = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$page) {
            return null;
        }
        
        // Parse konten jika menggunakan shortcode
        $page['content'] = $this->parseShortcodes($page['content']);
        
        return $page;
    }
    
    /**
     * Dapatkan semua halaman yang dipublikasikan
     */
    public function getPublishedPages(): array
    {
        $stmt = $this->db->pdo()->query("
            SELECT id, title, slug, excerpt, featured_image, created_at
            FROM pages
            WHERE is_published = 1
            ORDER BY created_at DESC
        ");
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Simpan halaman (untuk admin)
     */
    public function savePage(array $data): int
    {
        if (isset($data['id']) && $data['id'] > 0) {
            // Update
            $stmt = $this->db->pdo()->prepare("
                UPDATE pages SET
                    title = :title,
                    slug = :slug,
                    content = :content,
                    excerpt = :excerpt,
                    featured_image = :featured_image,
                    meta_title = :meta_title,
                    meta_description = :meta_description,
                    meta_keywords = :meta_keywords,
                    is_published = :is_published,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute($data);
            return (int)$data['id'];
        } else {
            // Insert
            $stmt = $this->db->pdo()->prepare("
                INSERT INTO pages (title, slug, content, excerpt, featured_image,
                                  meta_title, meta_description, meta_keywords,
                                  is_published, created_at, updated_at)
                VALUES (:title, :slug, :content, :excerpt, :featured_image,
                        :meta_title, :meta_description, :meta_keywords,
                        :is_published, NOW(), NOW())
            ");
            $stmt->execute($data);
            return (int)$this->db->pdo()->lastInsertId();
        }
    }
    
    /**
     * Hapus halaman
     */
    public function deletePage(int $id): bool
    {
        $stmt = $this->db->pdo()->prepare("DELETE FROM pages WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
    
    /**
     * Parse shortcodes dalam konten
     */
    private function parseShortcodes(string $content): string
    {
        // Contoh: [site_name] diganti dengan nama situs
        $config = $this->getSiteConfig();
        
        $shortcodes = [
            '[site_name]' => $config['site_name'] ?? 'Wizdam AI-Sikola',
            '[site_url]' => $config['site_url'] ?? 'https://www.sangia.org',
            '[current_year]' => date('Y'),
        ];
        
        return str_replace(array_keys($shortcodes), array_values($shortcodes), $content);
    }
    
    /**
     * Dapatkan daftar menu navigasi
     */
    public function getNavigationMenu(string $location = 'main'): array
    {
        $stmt = $this->db->pdo()->prepare("
            SELECT id, label, url, parent_id, sort_order, is_active
            FROM navigation_menu
            WHERE location = :location AND is_active = 1
            ORDER BY parent_id ASC, sort_order ASC
        ");
        $stmt->execute(['location' => $location]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Build tree structure
        return $this->buildMenuTree($items);
    }
    
    /**
     * Build menu tree dari flat list
     */
    private function buildMenuTree(array $items, int $parentId = 0): array
    {
        $tree = [];
        
        foreach ($items as $item) {
            if ($item['parent_id'] == $parentId) {
                $children = $this->buildMenuTree($items, (int)$item['id']);
                if (!empty($children)) {
                    $item['children'] = $children;
                }
                $tree[] = $item;
            }
        }
        
        return $tree;
    }
}
