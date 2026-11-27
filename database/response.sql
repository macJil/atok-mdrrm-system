USE atok_mdrrm_rebuild;
CREATE TABLE IF NOT EXISTS response_teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
    contact_phone VARCHAR(13) NOT NULL DEFAULT '',
    active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS incident_assignments (
    incident_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    status ENUM('Assigned','Completed','Cancelled') NOT NULL DEFAULT 'Assigned',
    PRIMARY KEY (incident_id,team_id),
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (team_id) REFERENCES response_teams(id)
) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS response_actions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    incident_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NULL,
    staff_id INT UNSIGNED NOT NULL,
    action_taken VARCHAR(60) NOT NULL,
    remarks VARCHAR(500) NOT NULL,
    incident_status VARCHAR(20) NOT NULL,
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (team_id) REFERENCES response_teams(id),
    FOREIGN KEY (staff_id) REFERENCES accounts(id)
) ENGINE=InnoDB;
