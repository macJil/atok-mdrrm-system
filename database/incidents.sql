USE atok_mdrrm_rebuild;
-- Run once after households.sql. Existing accounts may have no personal phone yet.
ALTER TABLE accounts ADD COLUMN phone VARCHAR(13) NULL;
CREATE TABLE incidents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    submitted_by INT UNSIGNED NOT NULL,
    source ENUM('Resident','Phone','Walk-in') NOT NULL,
    contact_name VARCHAR(100) NOT NULL,
    contact_phone VARCHAR(13) NOT NULL,
    incident_type VARCHAR(30) NOT NULL,
    barangay VARCHAR(40) NULL,
    location VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'Pending',
    reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submitted_by) REFERENCES accounts(id)
) ENGINE=InnoDB;
CREATE TABLE incident_images (
    incident_id INT UNSIGNED PRIMARY KEY,
    image_data MEDIUMBLOB NOT NULL,
    mime VARCHAR(20) NOT NULL,
    FOREIGN KEY (incident_id) REFERENCES incidents(id)
) ENGINE=InnoDB;
