-- Back up the selected database and run once before installing the updated PHP.
CREATE TABLE IF NOT EXISTS login_attempts (
    attempt_key CHAR(64) PRIMARY KEY,
    failed_attempts TINYINT UNSIGNED NOT NULL,
    window_started_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX (updated_at)
) ENGINE=InnoDB;
