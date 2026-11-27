-- Fictional classroom database only. Import once into a fresh instance.
-- Staff: staff@atok.example.org / AtokClassStaff27!
-- Resident: mila@atok.example.org / AtokClassResident27!
CREATE DATABASE IF NOT EXISTS atok_mdrrm_classroom
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE atok_mdrrm_classroom;
CREATE TABLE accounts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(13) NULL,
    role ENUM('staff','resident') NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    must_change BOOLEAN NOT NULL DEFAULT TRUE,
    session_version INT UNSIGNED NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE households (
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barangay VARCHAR(40) NOT NULL,
    address VARCHAR(255) NOT NULL,
    emergency_name VARCHAR(100) NOT NULL,
    emergency_phone VARCHAR(13) NOT NULL,
    registry_reason VARCHAR(24) NOT NULL,
    vulnerability VARCHAR(40) NOT NULL,
    hazard_notes VARCHAR(500) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

CREATE TABLE household_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id INT UNSIGNED NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (household_id, id),
    FOREIGN KEY (household_id) REFERENCES households(id)
) ENGINE=InnoDB;

CREATE TABLE household_representatives (
    household_id INT UNSIGNED PRIMARY KEY,
    member_id INT UNSIGNED NOT NULL,
    FOREIGN KEY (household_id, member_id) REFERENCES household_members(household_id, id)
) ENGINE=InnoDB;

CREATE TABLE resident_enrollments (
    member_id INT UNSIGNED PRIMARY KEY,
    account_id INT UNSIGNED NOT NULL UNIQUE,
    FOREIGN KEY (member_id) REFERENCES household_members(id),
    FOREIGN KEY (account_id) REFERENCES accounts(id)
) ENGINE=InnoDB;

CREATE TABLE incidents (
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
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

CREATE TABLE response_teams (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL UNIQUE,
    contact_phone VARCHAR(13) NOT NULL DEFAULT '',
    active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;
CREATE TABLE incident_assignments (
    incident_id INT UNSIGNED NOT NULL,
    team_id INT UNSIGNED NOT NULL,
    status ENUM('Assigned','Completed','Cancelled') NOT NULL DEFAULT 'Assigned',
    PRIMARY KEY (incident_id,team_id),
    FOREIGN KEY (incident_id) REFERENCES incidents(id),
    FOREIGN KEY (team_id) REFERENCES response_teams(id)
) ENGINE=InnoDB;
CREATE TABLE response_actions (
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

CREATE TABLE evacuation_centers (
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    barangay VARCHAR(40) NOT NULL,
    address VARCHAR(255) NOT NULL,
    capacity INT UNSIGNED NOT NULL,
    status ENUM('Open','Closed') NOT NULL,
    UNIQUE (barangay,name)
) ENGINE=InnoDB;
CREATE TABLE evacuation_walkins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    phone VARCHAR(13) NOT NULL,
    UNIQUE (name,address,phone)
) ENGINE=InnoDB;
CREATE TABLE evacuation_stays (
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

CREATE TABLE relief_goods (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    unit VARCHAR(40) NOT NULL,
    minimum_stock INT UNSIGNED NOT NULL DEFAULT 0,
    balance INT UNSIGNED NOT NULL DEFAULT 0,
    UNIQUE (name, unit)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    good_id INT UNSIGNED NOT NULL,
    direction ENUM('Stock-in','Stock-out') NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    movement_date DATE NOT NULL,
    reason VARCHAR(255) NOT NULL,
    recorded_by INT UNSIGNED NOT NULL,
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (good_id) REFERENCES relief_goods(id),
    FOREIGN KEY (recorded_by) REFERENCES accounts(id),
    INDEX (movement_date,id)
) ENGINE=InnoDB;

CREATE TABLE relief_points (
    latitude DECIMAL(10,7),
    longitude DECIMAL(10,7),
    active BOOLEAN NOT NULL DEFAULT TRUE,
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    barangay VARCHAR(40) NOT NULL,
    address VARCHAR(255) NOT NULL,
    UNIQUE (barangay,name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE relief_distributions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    household_id INT UNSIGNED NOT NULL,
    point_id INT UNSIGNED NOT NULL,
    receiver VARCHAR(100) NOT NULL,
    staff_id INT UNSIGNED NOT NULL,
    distributed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    submission_key CHAR(32) NOT NULL UNIQUE,
    FOREIGN KEY (household_id) REFERENCES households(id),
    FOREIGN KEY (point_id) REFERENCES relief_points(id),
    FOREIGN KEY (staff_id) REFERENCES accounts(id)
) ENGINE=InnoDB;
CREATE TABLE distribution_items (
    distribution_id INT UNSIGNED NOT NULL,
    good_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    movement_id INT UNSIGNED NOT NULL UNIQUE,
    PRIMARY KEY (distribution_id,good_id),
    FOREIGN KEY (distribution_id) REFERENCES relief_distributions(id),
    FOREIGN KEY (good_id) REFERENCES relief_goods(id),
    FOREIGN KEY (movement_id) REFERENCES stock_movements(id)
) ENGINE=InnoDB;

CREATE TABLE announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category ENUM('Disaster advisory','Evacuation instructions','Emergency announcement','Preparedness reminder') NOT NULL,
    message TEXT NOT NULL,
    status ENUM('Draft','Published','Withdrawn') NOT NULL DEFAULT 'Draft',
    show_on_login BOOLEAN NOT NULL DEFAULT FALSE,
    published_at DATETIME DEFAULT NULL,
    INDEX (status, published_at, id)
) ENGINE=InnoDB;

START TRANSACTION;
INSERT INTO accounts(id,full_name,email,password_hash,phone,role,must_change) VALUES
(1,'MDRRMO Staff','staff@atok.example.org','$2y$10$/h6vlJJVxGjdSfsczbVZBey8.HzQ4Sgzx6ziAMkdvZr2xiB.R8EwW',NULL,'staff',0),
(2,'Mila Ramos','mila@atok.example.org','$2y$10$FuhjHxw2V0XeMRixaVeuH.kh4SVBD7/3qCc9UHvSRN.oVv.pYcmdi','09170000001','resident',0);
INSERT INTO households(id,barangay,address,emergency_name,emergency_phone,registry_reason,vulnerability,hazard_notes,latitude,longitude)
VALUES(1,'Paoay','Training lane, shelter approach','Mila Ramos','09170000001','Affected','Not assessed','Drainage obstruction reported',16.62366,120.76422);
INSERT INTO household_members(id,household_id,full_name) VALUES(1,1,'Mila Ramos'),(2,1,'Ari Ramos');
INSERT INTO household_representatives VALUES(1,1);
INSERT INTO resident_enrollments VALUES(1,2);
INSERT INTO incidents(id,submitted_by,source,contact_name,contact_phone,incident_type,barangay,location,description,status,latitude,longitude)
VALUES(1,2,'Resident','Mila Ramos','09170000001','Flooding','Paoay','Training lane','Water covers the footpath at the bend.','Pending',16.62366,120.76422),
(2,1,'Phone','Lina Cruz','09170000002','Road obstruction','Caliking','Exercise junction','A fallen branch blocks one lane.','Verified',16.525,120.694);
INSERT INTO response_teams(id,name) VALUES(1,'Municipal Field Team');
INSERT INTO response_actions(incident_id,staff_id,action_taken,remarks,incident_status) VALUES(2,1,'Verify only','Caller confirmed location.','Verified');
INSERT INTO evacuation_centers(id,name,barangay,address,capacity,status,latitude,longitude)
VALUES(1,'Training Assembly Hall','Paoay','Assembly lane',5,'Open',16.621,120.76);
INSERT INTO evacuation_walkins(id,name,address,phone) VALUES(1,'Nico Santos','Practice lane','09170000003');
INSERT INTO evacuation_stays(center_id,walkin_id,arrived_by) VALUES(1,1,1);
INSERT INTO relief_goods(id,name,unit,minimum_stock,balance) VALUES(1,'Food pack','packs',3,18),(2,'Drinking water','bottles',5,18);
INSERT INTO relief_points(id,name,barangay,address,latitude,longitude) VALUES(1,'Supply desk','Paoay','Assembly lane east',16.625,120.766);
INSERT INTO stock_movements(id,good_id,direction,quantity,movement_date,reason,recorded_by)
VALUES(1,1,'Stock-in',20,CURRENT_DATE,'Classroom opening delivery',1),(2,2,'Stock-in',20,CURRENT_DATE,'Classroom opening delivery',1),
(3,1,'Stock-out',2,CURRENT_DATE,'Household relief distribution',1),(4,2,'Stock-out',2,CURRENT_DATE,'Household relief distribution',1);
INSERT INTO relief_distributions(id,household_id,point_id,receiver,staff_id,submission_key) VALUES(1,1,1,'Ari Ramos',1,'00000000000000000000000000000001');
INSERT INTO distribution_items(distribution_id,good_id,quantity,movement_id) VALUES(1,1,2,3),(1,2,2,4);
INSERT INTO announcements(title,category,message,status,show_on_login,published_at)
VALUES('Assembly hall reception','Preparedness reminder','Bring your household details to the reception desk.','Published',1,NOW()),
('Volunteer roster update','Emergency announcement','Staff are checking availability.','Draft',0,NULL);
COMMIT;
