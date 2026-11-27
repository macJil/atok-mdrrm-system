USE atok_mdrrm_rebuild;
CREATE TABLE IF NOT EXISTS announcements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    category ENUM('Disaster advisory','Evacuation instructions','Emergency announcement','Preparedness reminder') NOT NULL,
    message TEXT NOT NULL,
    status ENUM('Draft','Published','Withdrawn') NOT NULL DEFAULT 'Draft',
    revision INT UNSIGNED NOT NULL DEFAULT 1,
    show_on_login BOOLEAN NOT NULL DEFAULT FALSE,
    published_at DATETIME DEFAULT NULL,
    INDEX (status, published_at, id)
) ENGINE=InnoDB;
