-- Back up the selected database and run once before installing the updated PHP.
-- Existing walk-ins remain private records without a saved map point.
ALTER TABLE evacuation_walkins
    ADD latitude DECIMAL(10,7) NULL,
    ADD longitude DECIMAL(10,7) NULL;

-- A correction is a new, linked movement; the unique key allows at most one
-- direct correction per movement while keeping the original row immutable.
ALTER TABLE stock_movements
    ADD corrects_movement_id INT UNSIGNED NULL UNIQUE,
    ADD CONSTRAINT fk_stock_movement_correction
        FOREIGN KEY (corrects_movement_id) REFERENCES stock_movements(id);
