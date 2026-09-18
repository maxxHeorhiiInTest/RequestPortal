-- Request Portal — MySQL schema.
-- UA: Імпортуйте цей файл, якщо не хочете користуватися install.php
--     (адміністратора тоді створіть вручну, див. README.md).
-- EN: Import this file if you prefer not to run install.php
--     (create the administrator manually afterwards, see README.md).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS rp_requests (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_code VARCHAR(20) NOT NULL,
    type ENUM('new', 'update') NOT NULL,
    target_location VARCHAR(1000) NOT NULL,
    description TEXT NOT NULL,
    extra_comment TEXT NULL,
    faculty VARCHAR(255) NOT NULL DEFAULT '',
    department VARCHAR(255) NOT NULL DEFAULT '',
    requester_name VARCHAR(160) NOT NULL,
    requester_contact VARCHAR(255) NOT NULL,
    status ENUM('new', 'in_progress', 'done', 'rejected') NOT NULL DEFAULT 'new',
    admin_note TEXT NULL,
    lang CHAR(2) NOT NULL DEFAULT 'uk',
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_public_code (public_code),
    KEY idx_status (status),
    KEY idx_type (type),
    KEY idx_created_at (created_at),
    KEY idx_ip_created (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rp_request_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_request (request_id),
    CONSTRAINT fk_files_request FOREIGN KEY (request_id)
        REFERENCES rp_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rp_request_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    request_id INT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    note TEXT NULL,
    actor VARCHAR(160) NOT NULL,
    actor_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_request_created (request_id, created_at),
    CONSTRAINT fk_history_request FOREIGN KEY (request_id)
        REFERENCES rp_requests (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rp_admin_users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(80) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    last_login_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rp_content_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    event_at DATETIME NOT NULL,
    department VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    responsible VARCHAR(160) NOT NULL,
    extra_info TEXT NULL,
    channels TEXT NULL,
    status ENUM('draft', 'planned', 'preparing', 'published', 'cancelled') NOT NULL DEFAULT 'draft',
    created_by VARCHAR(160) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_event_at (event_at),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rp_feedback (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    public_code VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    faculty VARCHAR(255) NOT NULL DEFAULT '',
    department VARCHAR(255) NOT NULL DEFAULT '',
    requester_name VARCHAR(160) NOT NULL,
    requester_phone VARCHAR(80) NOT NULL DEFAULT '',
    requester_contact VARCHAR(255) NOT NULL,
    requester_channel VARCHAR(20) NOT NULL DEFAULT '',
    status ENUM('new', 'in_progress', 'done', 'rejected') NOT NULL DEFAULT 'new',
    admin_note TEXT NULL,
    lang CHAR(2) NOT NULL DEFAULT 'uk',
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_feedback_code (public_code),
    KEY idx_status (status),
    KEY idx_created_at (created_at),
    KEY idx_ip_created (ip_address, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rp_feedback_files (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feedback_id INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_feedback (feedback_id),
    CONSTRAINT fk_feedback_files FOREIGN KEY (feedback_id)
        REFERENCES rp_feedback (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rp_feedback_history (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    feedback_id INT UNSIGNED NOT NULL,
    action VARCHAR(40) NOT NULL,
    old_value VARCHAR(255) NULL,
    new_value VARCHAR(255) NULL,
    note TEXT NULL,
    actor VARCHAR(160) NOT NULL,
    actor_ip VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_feedback_created (feedback_id, created_at),
    CONSTRAINT fk_feedback_history FOREIGN KEY (feedback_id)
        REFERENCES rp_feedback (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rp_page_visits (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    path VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NULL,
    session_id VARCHAR(128) NULL,
    user_agent VARCHAR(255) NULL,
    lang CHAR(2) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_created (created_at),
    KEY idx_path_created (path, created_at),
    KEY idx_ip_created (ip_address, created_at),
    KEY idx_session_path_created (session_id, path, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rp_hidden_holidays (
    holiday_key VARCHAR(80) NOT NULL,
    hidden_by VARCHAR(160) NOT NULL,
    hidden_at DATETIME NOT NULL,
    PRIMARY KEY (holiday_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
