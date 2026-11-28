USE atok_mdrrm_rebuild;
CREATE TABLE IF NOT EXISTS relief_goods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(40) NOT NULL,
    minimum_stock INT UNSIGNED NOT NULL DEFAULT 0,
    balance INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE (name, unit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    good_id INT UNSIGNED NOT NULL,
    direction ENUM('Stock-in','Stock-out') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    movement_date DATE NOT NULL,
    reason VARCHAR(255) NOT NULL,
    recorded_by INT UNSIGNED NOT NULL,
    corrects_movement_id INT UNSIGNED NULL UNIQUE,
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (good_id) REFERENCES relief_goods(id),
    FOREIGN KEY (recorded_by) REFERENCES accounts(id),
    FOREIGN KEY (corrects_movement_id) REFERENCES stock_movements(id),
    INDEX (movement_date,id)
) ENGINE=InnoDB;
