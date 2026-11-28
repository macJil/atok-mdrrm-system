USE atok_mdrrm_rebuild;
CREATE TABLE IF NOT EXISTS relief_points (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    barangay VARCHAR(40) NOT NULL,
    address VARCHAR(255) NOT NULL,
    UNIQUE (barangay,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS relief_distributions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id INT UNSIGNED NOT NULL,
    point_id INT UNSIGNED NOT NULL,
    receiver VARCHAR(100) NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    distributed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    barangay_at_issue VARCHAR(40) NULL,
    submission_key CHAR(32) NOT NULL UNIQUE,
    INDEX idx_relief_distributions_distributed_at (distributed_at),
    FOREIGN KEY (household_id) REFERENCES households(id),
    FOREIGN KEY (point_id) REFERENCES relief_points(id),
    FOREIGN KEY (staff_id) REFERENCES accounts(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS distribution_items (
    distribution_id INT UNSIGNED NOT NULL,
    good_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    movement_id INT UNSIGNED NOT NULL UNIQUE,
    PRIMARY KEY (distribution_id,good_id),
    FOREIGN KEY (distribution_id) REFERENCES relief_distributions(id),
    FOREIGN KEY (good_id) REFERENCES relief_goods(id),
    FOREIGN KEY (movement_id) REFERENCES stock_movements(id)
) ENGINE=InnoDB;
