-- ============================================================
-- Wizdam Sikola — DB Migration v2
-- Tabel pendukung integrasi Sangia API Engine
-- Jalankan setelah database_schema_full.sql
-- ============================================================

-- ── 1. Sangia API key di tabel users ─────────────────────────────────────────
-- Tambahkan kolom ke tabel users yang sudah ada
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS sangia_api_key      VARCHAR(80)  DEFAULT NULL COMMENT 'wz_{user_id}_{ts}_{hmac16}',
    ADD COLUMN IF NOT EXISTS api_key_name        VARCHAR(100) DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS api_key_created_at  DATETIME     DEFAULT NULL;

-- ── 2. Cache profil author (ORCID + Scopus) ──────────────────────────────────
CREATE TABLE IF NOT EXISTS author_profiles_cache (
    orcid        VARCHAR(19)  NOT NULL,
    person_data  JSON         DEFAULT NULL COMMENT 'Data person dari ORCID',
    works_data   JSON         DEFAULT NULL COMMENT 'Array karya dari ORCID',
    scopus_data  JSON         DEFAULT NULL COMMENT 'Data author + publikasi dari Scopus',
    fetched_at   DATETIME     NOT NULL,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (orcid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 3. Cache sitasi per DOI ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS citations_cache (
    doi          VARCHAR(255) NOT NULL,
    metadata     JSON         DEFAULT NULL COMMENT 'Metadata artikel',
    citations    JSON         DEFAULT NULL COMMENT 'Array citing DOI per sumber',
    counts       JSON         DEFAULT NULL COMMENT '{opencitations: N, crossref: N, ...}',
    fetched_at   DATETIME     NOT NULL,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (doi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 4. Cache metrik jurnal (Scopus + SINTA) ───────────────────────────────────
CREATE TABLE IF NOT EXISTS journal_profiles_cache (
    issn         VARCHAR(10)  NOT NULL,
    scopus_data  JSON         DEFAULT NULL COMMENT 'CiteScore, SJR, SNIP, quartile',
    sinta_data   JSON         DEFAULT NULL COMMENT 'Grade SINTA, sinta_id, impact',
    fetched_at   DATETIME     NOT NULL,
    updated_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (issn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 5. Riwayat analisis (SDG, impact, trend, policy) ─────────────────────────
CREATE TABLE IF NOT EXISTS analysis_history (
    id            BIGINT       NOT NULL AUTO_INCREMENT,
    orcid         VARCHAR(19)  DEFAULT NULL,
    analysis_type VARCHAR(50)  NOT NULL COMMENT 'sdg|impact|trend|recommendation',
    result        JSON         NOT NULL,
    calculated_at DATETIME     NOT NULL,
    PRIMARY KEY (id),
    INDEX idx_orcid (orcid),
    INDEX idx_type  (analysis_type),
    INDEX idx_date  (calculated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 6. Input data Social & Economic pillar dari user/admin/crawler ────────────
CREATE TABLE IF NOT EXISTS researcher_impact_inputs (
    id          INT          NOT NULL AUTO_INCREMENT,
    orcid       VARCHAR(19)  NOT NULL,
    input_type  ENUM('social', 'economic') NOT NULL,
    field_key   VARCHAR(50)  NOT NULL COMMENT 'media_mentions|policy_citations|industry_adoption|patents|...',
    value       DECIMAL(5,2) NOT NULL COMMENT '0.00 – 100.00',
    source      ENUM('user_input', 'crawler', 'admin', 'api') DEFAULT 'user_input',
    verified    TINYINT(1)   DEFAULT 0,
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_orcid_type_field (orcid, input_type, field_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 7. Konfigurasi bobot analisis (dikelola via Admin Panel) ──────────────────
CREATE TABLE IF NOT EXISTS analysis_weight_configs (
    id          INT          NOT NULL AUTO_INCREMENT,
    config_key  VARCHAR(50)  NOT NULL COMMENT 'sdg_v5|sdg_v4|impact_composite|...',
    weights     JSON         NOT NULL,
    updated_by  INT          DEFAULT NULL COMMENT 'FK ke users.id',
    updated_at  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed: nilai default bobot
INSERT IGNORE INTO analysis_weight_configs (config_key, weights) VALUES
('sdg_v5', JSON_OBJECT(
    'keyword', 0.30, 'similarity', 0.30, 'substantive', 0.20, 'causal', 0.20,
    'max_sdgs', 7,
    'thresholds', JSON_OBJECT('min', 0.20, 'confidence', 0.30, 'high', 0.60)
)),
('impact_composite', JSON_OBJECT(
    'academic', 0.40, 'social', 0.25, 'economic', 0.20, 'sdg', 0.15
));

-- ── 8. Log panggilan ke Sangia API (monitoring efisiensi) ─────────────────────
CREATE TABLE IF NOT EXISTS api_call_logs (
    id           BIGINT       NOT NULL AUTO_INCREMENT,
    user_id      INT          DEFAULT NULL,
    endpoint     VARCHAR(100) NOT NULL,
    params       JSON         DEFAULT NULL,
    status       VARCHAR(20)  NOT NULL COMMENT 'success|error|processing',
    duration_ms  INT          DEFAULT NULL,
    data_source  VARCHAR(50)  DEFAULT NULL COMMENT 'wizdam_sikola_db|orcid_api|scopus_api|...',
    called_at    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    INDEX idx_user    (user_id),
    INDEX idx_called  (called_at),
    INDEX idx_source  (data_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── 9. Link user ke ORCID/Scopus (untuk sync otomatis) ───────────────────────
CREATE TABLE IF NOT EXISTS user_researcher_links (
    user_id       INT         NOT NULL,
    researcher_id INT         NOT NULL,
    linked_at     TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, researcher_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
