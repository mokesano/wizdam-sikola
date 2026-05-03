-- Database Schema untuk Wizdam Sicola
-- MariaDB 10.6+

-- Tabel Users (menggunakan delight-im auth)
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    username VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255),
    avatar_url VARCHAR(500),
    bio TEXT,
    institution_id INT UNSIGNED,
    role ENUM('user', 'admin', 'super_admin') DEFAULT 'user',
    subscription_tier ENUM('free', 'pro', 'enterprise') DEFAULT 'free',
    subscription_expires_at DATETIME NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_username (username),
    INDEX idx_institution (institution_id),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Institutions
CREATE TABLE IF NOT EXISTS institutions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    short_name VARCHAR(100),
    type ENUM('university', 'research_center', 'government', 'private', 'international') NOT NULL,
    country VARCHAR(100) DEFAULT 'Indonesia',
    province VARCHAR(100),
    city VARCHAR(100),
    address TEXT,
    website VARCHAR(255),
    logo_url VARCHAR(500),
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    orcid_id VARCHAR(50),
    ror_id VARCHAR(50),
    grid_id VARCHAR(50),
    total_researchers INT UNSIGNED DEFAULT 0,
    total_publications INT UNSIGNED DEFAULT 0,
    wizdam_score DECIMAL(10, 2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_type (type),
    INDEX idx_country (country),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Researchers
CREATE TABLE IF NOT EXISTS researchers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    institution_id INT UNSIGNED NULL,
    full_name VARCHAR(255) NOT NULL,
    preferred_name VARCHAR(255),
    email VARCHAR(255),
    orcid_id VARCHAR(50) UNIQUE,
    scopus_id VARCHAR(50),
    sinta_id VARCHAR(50),
    google_scholar_id VARCHAR(100),
    researchgate_url VARCHAR(255),
    website_url VARCHAR(255),
    bio TEXT,
    profile_image_url VARCHAR(500),
    cover_image_url VARCHAR(500),
    position_title VARCHAR(255),
    department VARCHAR(255),
    field_of_study JSON,
    expertise_tags JSON,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    total_publications INT UNSIGNED DEFAULT 0,
    total_citations INT UNSIGNED DEFAULT 0,
    h_index INT UNSIGNED DEFAULT 0,
    i10_index INT UNSIGNED DEFAULT 0,
    wizdam_score DECIMAL(10, 2) DEFAULT 0,
    wizdam_percentile DECIMAL(5, 2) DEFAULT 0,
    sdgs_primary_goals JSON,
    is_claimed BOOLEAN DEFAULT FALSE,
    last_sync_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE SET NULL,
    INDEX idx_name (full_name),
    INDEX idx_institution (institution_id),
    INDEX idx_orcid (orcid_id),
    INDEX idx_wizdam_score (wizdam_score),
    INDEX idx_location (latitude, longitude)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Publications
CREATE TABLE IF NOT EXISTS publications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    doi VARCHAR(100) UNIQUE,
    title TEXT NOT NULL,
    abstract TEXT,
    publication_date DATE,
    publication_year INT UNSIGNED,
    journal_title VARCHAR(255),
    publisher VARCHAR(255),
    volume VARCHAR(50),
    issue VARCHAR(50),
    pages VARCHAR(50),
    issn VARCHAR(20),
    isbn VARCHAR(20),
    document_type ENUM('article', 'conference', 'book', 'book_chapter', 'thesis', 'report', 'other') DEFAULT 'article',
    access_type ENUM('open_access', 'subscription', 'hybrid') DEFAULT 'subscription',
    license VARCHAR(100),
    pdf_url VARCHAR(500),
    fulltext_url VARCHAR(500),
    cited_by_count INT UNSIGNED DEFAULT 0,
    references_count INT UNSIGNED DEFAULT 0,
    wizdam_score DECIMAL(10, 2) DEFAULT 0,
    sdgs_goals JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_doi (doi),
    INDEX idx_year (publication_year),
    INDEX idx_journal (journal_title),
    INDEX idx_wizdam_score (wizdam_score),
    FULLTEXT idx_title_abstract (title, abstract)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Publication Authors (many-to-many)
CREATE TABLE IF NOT EXISTS publication_authors (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    publication_id BIGINT UNSIGNED NOT NULL,
    researcher_id INT UNSIGNED NULL,
    author_name VARCHAR(255) NOT NULL,
    author_order INT UNSIGNED NOT NULL,
    is_corresponding BOOLEAN DEFAULT FALSE,
    affiliation VARCHAR(255),
    orcid_id VARCHAR(50),
    FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE,
    FOREIGN KEY (researcher_id) REFERENCES researchers(id) ON DELETE SET NULL,
    INDEX idx_publication (publication_id),
    INDEX idx_researcher (researcher_id),
    INDEX idx_author_order (author_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel API Keys
CREATE TABLE IF NOT EXISTS api_keys (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    api_key VARCHAR(100) NOT NULL UNIQUE,
    api_secret VARCHAR(255) NOT NULL,
    permissions JSON,
    expires_at DATETIME NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_api_key (api_key),
    INDEX idx_user (user_id),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Jobs (Queue)
CREATE TABLE IF NOT EXISTS jobs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id VARCHAR(100) NOT NULL UNIQUE,
    class VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    priority TINYINT UNSIGNED DEFAULT 5,
    attempts TINYINT UNSIGNED DEFAULT 0,
    max_attempts TINYINT UNSIGNED DEFAULT 3,
    progress TINYINT UNSIGNED DEFAULT 0,
    result TEXT NULL,
    error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    available_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_job_id (job_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_available_at (available_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Citations
CREATE TABLE IF NOT EXISTS citations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    citing_publication_id BIGINT UNSIGNED NULL,
    cited_publication_id BIGINT UNSIGNED NOT NULL,
    citation_source ENUM('openalex', 'opencitations', 'crossref', 'scopus', 'manual') NOT NULL,
    external_id VARCHAR(100),
    citation_date DATE,
    citation_year INT UNSIGNED,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (citing_publication_id) REFERENCES publications(id) ON DELETE SET NULL,
    FOREIGN KEY (cited_publication_id) REFERENCES publications(id) ON DELETE CASCADE,
    INDEX idx_citing (citing_publication_id),
    INDEX idx_cited (cited_publication_id),
    INDEX idx_source (citation_source),
    UNIQUE KEY uniq_citation (citing_publication_id, cited_publication_id, citation_source)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Altmetrics (News, Policy, Social Media)
CREATE TABLE IF NOT EXISTS altmetrics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('researcher', 'publication', 'institution') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    source ENUM('news', 'policy', 'twitter', 'facebook', 'linkedin', 'reddit', 'wikipedia', 'mendeley') NOT NULL,
    source_id VARCHAR(100),
    title VARCHAR(500),
    url VARCHAR(500),
    mention_count INT UNSIGNED DEFAULT 1,
    sentiment ENUM('positive', 'neutral', 'negative') DEFAULT 'neutral',
    language VARCHAR(10),
    country VARCHAR(100),
    published_at DATETIME,
    crawled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_source (source),
    INDEX idx_published_at (published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel User Activities (Feed)
CREATE TABLE IF NOT EXISTS user_activities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    activity_type ENUM('profile_update', 'publication_added', 'citation_received', 'score_updated', 'follow', 'like', 'share') NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT UNSIGNED,
    description TEXT,
    metadata JSON,
    visibility ENUM('public', 'followers', 'private') DEFAULT 'public',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Follows
CREATE TABLE IF NOT EXISTS follows (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    follower_user_id INT UNSIGNED NOT NULL,
    following_user_id INT UNSIGNED NOT NULL,
    following_researcher_id INT UNSIGNED NULL,
    following_institution_id INT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (follower_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_researcher_id) REFERENCES researchers(id) ON DELETE CASCADE,
    FOREIGN KEY (following_institution_id) REFERENCES institutions(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_follow (follower_user_id, following_user_id, following_researcher_id, following_institution_id),
    INDEX idx_follower (follower_user_id),
    INDEX idx_following (following_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabel Settings
CREATE TABLE IF NOT EXISTS settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'Wizdam Sicola', 'string', 'Nama situs'),
('site_description', 'Platform Pengukuran Dampak Riset', 'string', 'Deskripsi situs'),
('maintenance_mode', 'false', 'boolean', 'Mode maintenance'),
('registration_enabled', 'true', 'boolean', 'Izinkan registrasi pengguna baru'),
('default_subscription_tier', 'free', 'string', 'Tier subscription default');
