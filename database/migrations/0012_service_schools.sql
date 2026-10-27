CREATE TABLE service_schools (
 service_id BIGINT UNSIGNED NOT NULL, school_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(service_id,school_id),
 FOREIGN KEY(service_id) REFERENCES services(id) ON DELETE CASCADE,
 FOREIGN KEY(school_id) REFERENCES schools(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT IGNORE INTO service_schools(service_id,school_id)
 SELECT id,school_id FROM services WHERE school_id IS NOT NULL;
