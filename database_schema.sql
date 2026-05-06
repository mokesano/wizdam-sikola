-- ============================================================
-- Wizdam Scola – Skema Database
-- MySQL 8.0+ / MariaDB 10.6+
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── Tabel Autentikasi (Delight-IM/Auth) ─────────────────────
-- Tabel ini dibuat otomatis oleh library Delight-IM.
-- Jalankan: php -r "require 'vendor/autoload.php'; (new \Delight\Auth\Auth(...))->install();"

-- ── Institusi ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `institutions` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name`              VARCHAR(255) NOT NULL,
    `acronym`           VARCHAR(20)  DEFAULT NULL,
    `type`              ENUM('universitas','institut','politeknik','sekolah_tinggi','akademi','lembaga_riset','lainnya') DEFAULT 'universitas',
    `province`          VARCHAR(100) DEFAULT NULL,
    `city`              VARCHAR(100) DEFAULT NULL,
    `sinta_id`          VARCHAR(50)  DEFAULT NULL UNIQUE,
    `scopus_affil_id`   VARCHAR(50)  DEFAULT NULL,
    `total_researchers` INT UNSIGNED DEFAULT 0,
    `avg_impact_score`  DECIMAL(8,4) DEFAULT 0.0000,
    `created_at`        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_province (`province`),
    INDEX idx_sinta    (`sinta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Peneliti ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `researchers` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `orcid`            VARCHAR(20)  NOT NULL UNIQUE,
    `name`             VARCHAR(255) NOT NULL,
    `affiliation_id`   INT UNSIGNED DEFAULT NULL,
    `sinta_id`         VARCHAR(50)  DEFAULT NULL,
    `scopus_id`        VARCHAR(50)  DEFAULT NULL,
    `research_field`   VARCHAR(100) DEFAULT NULL,
    `biography`        TEXT         DEFAULT NULL,
    `h_index`          SMALLINT UNSIGNED DEFAULT 0,
    `i10_index`        SMALLINT UNSIGNED DEFAULT 0,
    `total_citations`  INT UNSIGNED DEFAULT 0,
    `works_count`      SMALLINT UNSIGNED DEFAULT 0,
    `impact_score`     DECIMAL(8,4) DEFAULT 0.0000,
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`affiliation_id`) REFERENCES `institutions`(`id`) ON DELETE SET NULL,
    INDEX idx_orcid         (`orcid`),
    INDEX idx_impact_score  (`impact_score` DESC),
    INDEX idx_research_field(`research_field`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Link User–Peneliti ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_researcher_links` (
    `user_id`       INT UNSIGNED NOT NULL,
    `researcher_id` INT UNSIGNED NOT NULL,
    `linked_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `researcher_id`),
    FOREIGN KEY (`researcher_id`) REFERENCES `researchers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Jurnal ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `journals` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title`           VARCHAR(500) NOT NULL,
    `issn`            VARCHAR(9)   DEFAULT NULL,
    `e_issn`          VARCHAR(9)   DEFAULT NULL,
    `publisher`       VARCHAR(255) DEFAULT NULL,
    `sinta_rank`      TINYINT UNSIGNED DEFAULT NULL COMMENT '1-6',
    `sinta_score`     DECIMAL(10,4)    DEFAULT NULL,
    `scopus_sjr`      DECIMAL(10,4)    DEFAULT NULL,
    `wos_jif`         DECIMAL(10,4)    DEFAULT NULL,
    `is_predatory`    TINYINT(1)       DEFAULT 0,
    `total_articles`  INT UNSIGNED     DEFAULT 0,
    `created_at`      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME         DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_issn  (`issn`),
    INDEX idx_sinta_rank(`sinta_rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Artikel ──────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `articles` (
    `id`               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title`            TEXT         NOT NULL,
    `doi`              VARCHAR(255) DEFAULT NULL UNIQUE,
    `journal_id`       INT UNSIGNED DEFAULT NULL,
    `year`             SMALLINT UNSIGNED DEFAULT NULL,
    `abstract`         TEXT         DEFAULT NULL,
    `citations`        INT UNSIGNED DEFAULT 0,
    `social_mentions`  INT UNSIGNED DEFAULT 0,
    `practical_uses`   INT UNSIGNED DEFAULT 0,
    `impact_score`     DECIMAL(8,4) DEFAULT 0.0000,
    `authors_snapshot` TEXT         DEFAULT NULL COMMENT 'JSON: [{name, orcid}]',
    `sdg_tags`         TEXT         DEFAULT NULL COMMENT 'JSON: [{sdg, score}]',
    `created_at`       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME     DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`journal_id`) REFERENCES `journals`(`id`) ON DELETE SET NULL,
    INDEX idx_year         (`year`),
    INDEX idx_citations    (`citations` DESC),
    INDEX idx_impact_score (`impact_score` DESC)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Relasi Artikel–Peneliti ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `article_authors` (
    `article_id`    INT UNSIGNED NOT NULL,
    `researcher_id` INT UNSIGNED NOT NULL,
    `author_order`  TINYINT UNSIGNED DEFAULT 1,
    PRIMARY KEY (`article_id`, `researcher_id`),
    FOREIGN KEY (`article_id`)    REFERENCES `articles`(`id`)    ON DELETE CASCADE,
    FOREIGN KEY (`researcher_id`) REFERENCES `researchers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Impact Scores (4 Pilar) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `impact_scores` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entity_type`     ENUM('researcher','article','institution','journal') NOT NULL,
    `entity_id`       INT UNSIGNED NOT NULL,
    `pillar_academic` DECIMAL(8,4) NOT NULL DEFAULT 0.0000 COMMENT '40% bobot',
    `pillar_social`   DECIMAL(8,4) NOT NULL DEFAULT 0.0000 COMMENT '25% bobot',
    `pillar_economic` DECIMAL(8,4) NOT NULL DEFAULT 0.0000 COMMENT '20% bobot',
    `pillar_sdg`      DECIMAL(8,4) NOT NULL DEFAULT 0.0000 COMMENT '15% bobot',
    `composite_score` DECIMAL(8,4) NOT NULL DEFAULT 0.0000,
    `sdg_tags`        TEXT         DEFAULT NULL COMMENT 'JSON array SDG matches',
    `calculated_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity        (`entity_type`, `entity_id`),
    INDEX idx_calculated_at (`calculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Notifikasi ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `message`    TEXT         NOT NULL,
    `link`       VARCHAR(500) DEFAULT NULL,
    `is_read`    TINYINT(1)   DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Log Aktivitas ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED DEFAULT NULL,
    `action`      VARCHAR(100) NOT NULL,
    `description` TEXT         DEFAULT NULL,
    `ip_address`  VARCHAR(45)  DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
