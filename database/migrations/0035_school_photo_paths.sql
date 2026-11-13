-- School photos copied from the Madares upload tree: uploads/schools/YYYY/MM/<filename>.
-- Store paths, never image blobs. Existing school rows are unchanged.
ALTER TABLE schools ADD COLUMN photo_path VARCHAR(255) NULL;
ALTER TABLE school_catalog ADD COLUMN photo_path VARCHAR(255) NULL;
