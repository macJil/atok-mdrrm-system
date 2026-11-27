USE atok_mdrrm_rebuild;
-- Apply once after the module schemas. Existing written addresses remain valid.
ALTER TABLE incidents ADD latitude DECIMAL(10,7), ADD longitude DECIMAL(10,7);
ALTER TABLE households ADD latitude DECIMAL(10,7), ADD longitude DECIMAL(10,7);
ALTER TABLE evacuation_centers ADD latitude DECIMAL(10,7), ADD longitude DECIMAL(10,7);
ALTER TABLE relief_points ADD latitude DECIMAL(10,7), ADD longitude DECIMAL(10,7), ADD active BOOLEAN NOT NULL DEFAULT TRUE;
