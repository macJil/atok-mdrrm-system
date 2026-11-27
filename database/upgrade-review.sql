-- Run once on the selected existing database, before installing the updated PHP.
-- Earlier distributions have no verified historical barangay. Preserve NULL.
ALTER TABLE announcements ADD revision INT UNSIGNED NOT NULL DEFAULT 1;
ALTER TABLE relief_distributions ADD barangay_at_issue VARCHAR(40) NULL;
