USE atok_mdrrm_rebuild;
CREATE TABLE IF NOT EXISTS evacuation_centers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    barangay VARCHAR(40) NOT NULL,
    address VARCHAR(255) NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    status ENUM('Open','Closed') NOT NULL,
    UNIQUE (barangay,name)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS evacuation_walkins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(13) NOT NULL,
    UNIQUE (name,address,phone)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS evacuation_stays (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    center_id INT UNSIGNED NOT NULL,
    member_id INT UNSIGNED NULL,
    walkin_id INT UNSIGNED NULL,
    arrived_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    arrived_by INT UNSIGNED NOT NULL,
    left_at TIMESTAMP NULL,
    checked_out_by INT UNSIGNED NULL,
    active_stay TINYINT GENERATED ALWAYS AS (IF(left_at IS NULL,1,NULL)) STORED,
    CHECK ((member_id IS NULL) <> (walkin_id IS NULL)),
    UNIQUE (member_id,active_stay),
    UNIQUE (walkin_id,active_stay),
    INDEX (center_id,left_at),
    FOREIGN KEY (center_id) REFERENCES evacuation_centers(id),
    FOREIGN KEY (member_id) REFERENCES household_members(id),
    FOREIGN KEY (walkin_id) REFERENCES evacuation_walkins(id),
    FOREIGN KEY (arrived_by) REFERENCES accounts(id),
    FOREIGN KEY (checked_out_by) REFERENCES accounts(id)
) ENGINE=InnoDB;
