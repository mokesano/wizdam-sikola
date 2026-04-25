-- ============================================================
-- Wizdam AI-Sikola – Skema Database v2
-- MySQL 8.0+ / MariaDB 10.6+
-- 17 Tabel | 4 Domain + Operasional
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ── DOMAIN 1: CORE ENTITIES ──────────────────────────────────

-- Tabel users dikelola otomatis oleh Delight-IM/Auth.
-- Jalankan: php -r "require 'vendor/autoload.php'; (new \Delight\Auth\Auth(...))->install();"

CREATE TABLE IF NOT EXISTS `institutions` (
    `id`           INT UNSIGNED     AUTO_INCREMENT PRIMARY KEY,
    `name`         VARCHAR(255)     NOT NULL,
    `acronym`      VARCHAR(20)      DEFAULT NULL,
    `type`         ENUM('universitas','institut','politeknik','sekolah_tinggi',
                        'akademi','lembaga_riset','lainnya') DEFAULT 'universitas',
    `country_code` CHAR(2)          DEFAULT 'ID',
    `province`     VARCHAR(100)     DEFAULT NULL,
    `city`         VARCHAR(100)     DEFAULT NULL,
    `created_at`   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   DATETIME         DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_country_province (`country_code`, `province`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `researchers` (
    `id`                     INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    `user_id`                INT UNSIGNED  DEFAULT NULL COMMENT 'NULL = profil belum diklaim pemiliknya',
    `orcid`                  VARCHAR(20)   NOT NULL UNIQUE,
    `full_name`              VARCHAR(255)  NOT NULL,
    `primary_institution_id` INT UNSIGNED  DEFAULT NULL,
    `research_field`         VARCHAR(100)  DEFAULT NULL,
    `biography`              TEXT          DEFAULT NULL,
    `created_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`             DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`primary_institution_id`) REFERENCES `institutions`(`id`) ON DELETE SET NULL,
    INDEX idx_orcid   (`orcid`),
    INDEX idx_user_id (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `journals` (
    `id`             INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    `issn_p`         VARCHAR(9)    DEFAULT NULL COMMENT 'ISSN cetak',
    `issn_e`         VARCHAR(9)    DEFAULT NULL COMMENT 'ISSN elektronik',
    `title`          VARCHAR(500)  NOT NULL,
    `publisher_name` VARCHAR(255)  DEFAULT NULL,
    `is_predatory`   TINYINT(1)    DEFAULT 0,
    `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_issn_p (`issn_p`),
    INDEX idx_title  (`title`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `external_identifiers` (
    `id`            INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
    `entity_type`   ENUM('researcher','institution','journal') NOT NULL,
    `entity_id`     INT UNSIGNED  NOT NULL,
    `provider`      ENUM('scopus','sinta','wos','crossref','pubmed',
                         'google_scholar','garuda','dimensions','other') NOT NULL,
    `provider_id`   VARCHAR(255)  NOT NULL,
    `metadata_json` JSON          DEFAULT NULL COMMENT 'Cache mentah: h-index, quartile, SJR, dsb.',
    `fetched_at`    DATETIME      DEFAULT NULL,
    `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME      DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_entity_provider (`entity_type`, `entity_id`, `provider`),
    INDEX idx_entity   (`entity_type`, `entity_id`),
    INDEX idx_provider (`provider`, `provider_id`(100))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── DOMAIN 2: KARYA & PUBLIKASI ──────────────────────────────

CREATE TABLE IF NOT EXISTS `works` (
    `id`               INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `doi`              VARCHAR(255)   DEFAULT NULL UNIQUE,
    `title`            TEXT           NOT NULL,
    `publication_year` SMALLINT UNSIGNED DEFAULT NULL,
    `type`             ENUM('article','book','book_chapter','conference_paper',
                            'patent','thesis','dataset','other') DEFAULT 'article',
    `journal_id`       INT UNSIGNED   DEFAULT NULL,
    `abstract`         TEXT           DEFAULT NULL,
    `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME       DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`journal_id`) REFERENCES `journals`(`id`) ON DELETE SET NULL,
    INDEX idx_year (`publication_year`),
    INDEX idx_type (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `work_authors` (
    `work_id`        INT UNSIGNED  NOT NULL,
    `researcher_id`  INT UNSIGNED  NOT NULL,
    `institution_id` INT UNSIGNED  DEFAULT NULL COMMENT 'Afiliasi peneliti saat karya diterbitkan',
    `author_order`   TINYINT UNSIGNED DEFAULT 1,
    PRIMARY KEY (`work_id`, `researcher_id`),
    FOREIGN KEY (`work_id`)        REFERENCES `works`(`id`)        ON DELETE CASCADE,
    FOREIGN KEY (`researcher_id`)  REFERENCES `researchers`(`id`)  ON DELETE CASCADE,
    FOREIGN KEY (`institution_id`) REFERENCES `institutions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `work_sdg_labels` (
    `id`               INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `work_id`          INT UNSIGNED   NOT NULL,
    `sdg_code`         TINYINT UNSIGNED NOT NULL COMMENT '1–17 sesuai SDG PBB',
    `confidence_score` DECIMAL(4,3)   DEFAULT 0.000 COMMENT '0.000 – 1.000',
    `classified_by`    ENUM('sangia_api','manual') DEFAULT 'sangia_api',
    `created_at`       DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`work_id`) REFERENCES `works`(`id`) ON DELETE CASCADE,
    UNIQUE KEY uq_work_sdg (`work_id`, `sdg_code`),
    INDEX idx_sdg_code (`sdg_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── DOMAIN 3: BUKU BESAR DAMPAK ──────────────────────────────

CREATE TABLE IF NOT EXISTS `impact_academic` (
    `id`            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `work_id`       INT UNSIGNED   DEFAULT NULL,
    `researcher_id` INT UNSIGNED   DEFAULT NULL,
    `metric_type`   ENUM('citation','h_index','i10_index','scopus_doc_count',
                         'grant_amount','works_count') NOT NULL,
    `metric_value`  DECIMAL(14,4)  NOT NULL DEFAULT 0,
    `source`        ENUM('crossref','scopus_api','wos_api','sinta_api',
                         'manual_input','crawler') NOT NULL,
    `recorded_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`work_id`)       REFERENCES `works`(`id`)       ON DELETE CASCADE,
    FOREIGN KEY (`researcher_id`) REFERENCES `researchers`(`id`) ON DELETE CASCADE,
    INDEX idx_work       (`work_id`),
    INDEX idx_researcher (`researcher_id`),
    INDEX idx_metric     (`metric_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `impact_social` (
    `id`            INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `work_id`       INT UNSIGNED   DEFAULT NULL,
    `researcher_id` INT UNSIGNED   DEFAULT NULL,
    `platform`      ENUM('twitter','facebook','instagram','linkedin','youtube',
                         'news_media','tv','podcast','blog','other') NOT NULL,
    `mention_url`   VARCHAR(2048)  DEFAULT NULL,
    `mention_title` VARCHAR(500)   DEFAULT NULL,
    `sentiment`     ENUM('positive','neutral','negative') DEFAULT 'neutral',
    `reach_count`   INT UNSIGNED   DEFAULT 0 COMMENT 'Estimasi audiens/views',
    `recorded_at`   DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`work_id`)       REFERENCES `works`(`id`)       ON DELETE CASCADE,
    FOREIGN KEY (`researcher_id`) REFERENCES `researchers`(`id`) ON DELETE CASCADE,
    INDEX idx_platform    (`platform`),
    INDEX idx_recorded_at (`recorded_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `impact_policy` (
    `id`              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `work_id`         INT UNSIGNED   NOT NULL,
    `policy_title`    VARCHAR(500)   NOT NULL,
    `issuer_name`     VARCHAR(255)   NOT NULL COMMENT 'Contoh: WHO, Kementerian Kesehatan RI',
    `issuer_type`     ENUM('government','international_org','ngo',
                           'industry','academic') DEFAULT 'government',
    `year_adopted`    SMALLINT UNSIGNED DEFAULT NULL,
    `policy_url`      VARCHAR(2048)  DEFAULT NULL,
    `verified_status` TINYINT(1)    DEFAULT 0,
    `verified_by`     INT UNSIGNED   DEFAULT NULL COMMENT 'user_id admin yang memverifikasi',
    `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`work_id`) REFERENCES `works`(`id`) ON DELETE CASCADE,
    INDEX idx_work (`work_id`),
    INDEX idx_year (`year_adopted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `impact_practical` (
    `id`              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `work_id`         INT UNSIGNED   NOT NULL,
    `adoption_type`   ENUM('industry_usage','community_program','commercial_product',
                           'startup','government_program','other') NOT NULL,
    `adopter_name`    VARCHAR(255)   DEFAULT NULL,
    `description`     TEXT           DEFAULT NULL,
    `year_adopted`    SMALLINT UNSIGNED DEFAULT NULL,
    `verified_status` TINYINT(1)    DEFAULT 0,
    `verified_by`     INT UNSIGNED   DEFAULT NULL COMMENT 'user_id admin yang memverifikasi',
    `created_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`work_id`) REFERENCES `works`(`id`) ON DELETE CASCADE,
    INDEX idx_adoption_type (`adoption_type`),
    INDEX idx_year          (`year_adopted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── DOMAIN 4: WIZDAM IMPACT SCORE ────────────────────────────

CREATE TABLE IF NOT EXISTS `wizdam_scores` (
    `id`                 INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `entity_type`        ENUM('researcher','institution','journal','country') NOT NULL,
    `entity_id`          INT UNSIGNED   NOT NULL,
    `score_academic`     DECIMAL(8,4)   NOT NULL DEFAULT 0.0000 COMMENT 'Pilar 1 – Akademik',
    `score_social`       DECIMAL(8,4)   NOT NULL DEFAULT 0.0000 COMMENT 'Pilar 2 – Sosial',
    `score_policy`       DECIMAL(8,4)   NOT NULL DEFAULT 0.0000 COMMENT 'Pilar 3 – Kebijakan',
    `score_practical`    DECIMAL(8,4)   NOT NULL DEFAULT 0.0000 COMMENT 'Pilar 4 – Praktis',
    `total_impact_score` DECIMAL(8,4)   NOT NULL DEFAULT 0.0000 COMMENT 'Hasil akhir komposit Sangia API',
    `sdg_summary_json`   JSON           DEFAULT NULL COMMENT 'Top SDG tags dan skornya',
    `calculated_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity      (`entity_type`, `entity_id`),
    INDEX idx_total_score (`total_impact_score` DESC),
    INDEX idx_calc        (`calculated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── OPERASIONAL: HARVESTING ───────────────────────────────────

CREATE TABLE IF NOT EXISTS `harvesting_sources` (
    `id`                    INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `base_url`              VARCHAR(500)   NOT NULL,
    `set_spec`              VARCHAR(255)   DEFAULT NULL,
    `protocol`              ENUM('oai_pmh','rest_api','scraper') DEFAULT 'oai_pmh',
    `last_harvested_at`     DATETIME       DEFAULT NULL,
    `last_resumption_token` TEXT           DEFAULT NULL,
    `status`                ENUM('active','paused','error') DEFAULT 'active',
    `created_at`            DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_url_set (`base_url`(255), `set_spec`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `harvest_runs` (
    `id`              INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `source_id`       INT UNSIGNED   NOT NULL,
    `started_at`      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `finished_at`     DATETIME       DEFAULT NULL,
    `records_added`   INT UNSIGNED   DEFAULT 0,
    `records_updated` INT UNSIGNED   DEFAULT 0,
    `status`          ENUM('running','completed','failed') DEFAULT 'running',
    `error_message`   TEXT           DEFAULT NULL,
    FOREIGN KEY (`source_id`) REFERENCES `harvesting_sources`(`id`) ON DELETE CASCADE,
    INDEX idx_source     (`source_id`),
    INDEX idx_started_at (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── OPERASIONAL: UX & AUDIT ───────────────────────────────────

CREATE TABLE IF NOT EXISTS `notifications` (
    `id`         INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED   NOT NULL,
    `message`    TEXT           NOT NULL,
    `link`       VARCHAR(500)   DEFAULT NULL,
    `is_read`    TINYINT(1)     DEFAULT 0,
    `created_at` DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_unread (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`          INT UNSIGNED   AUTO_INCREMENT PRIMARY KEY,
    `user_id`     INT UNSIGNED   DEFAULT NULL,
    `action`      VARCHAR(100)   NOT NULL,
    `entity_type` VARCHAR(50)    DEFAULT NULL,
    `entity_id`   INT UNSIGNED   DEFAULT NULL,
    `description` TEXT           DEFAULT NULL,
    `ip_address`  VARCHAR(45)    DEFAULT NULL,
    `created_at`  DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user       (`user_id`),
    INDEX idx_created_at (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
