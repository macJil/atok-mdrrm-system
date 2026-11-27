USE atok_mdrrm_rebuild;
CREATE TABLE IF NOT EXISTS households (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barangay VARCHAR(40) NOT NULL,
    address VARCHAR(255) NOT NULL,
    emergency_name VARCHAR(100) NOT NULL,
    emergency_phone VARCHAR(13) NOT NULL,
    registry_reason VARCHAR(24) NOT NULL,
    vulnerability VARCHAR(40) NOT NULL,
    hazard_notes VARCHAR(500) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS household_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (household_id, id),
    FOREIGN KEY (household_id) REFERENCES households(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS household_representatives (
    household_id INT UNSIGNED PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (household_id, member_id) REFERENCES household_members(household_id, id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS resident_enrollments (
    member_id INT UNSIGNED PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL UNIQUE,
    FOREIGN KEY (member_id) REFERENCES household_members(id),
    FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;
