-- Back up the selected database and run once before installing the updated PHP.
-- These indexes support the date-range filters used by incident and summary reports.
ALTER TABLE incidents
    ADD INDEX idx_incidents_reported_at (reported_at);

ALTER TABLE evacuation_stays
    ADD INDEX idx_evacuation_stays_arrived_center (arrived_at,center_id);

ALTER TABLE relief_distributions
    ADD INDEX idx_relief_distributions_distributed_at (distributed_at);
