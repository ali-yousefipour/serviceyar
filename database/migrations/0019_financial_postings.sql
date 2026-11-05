CREATE TABLE financial_postings (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 agency_id BIGINT UNSIGNED NOT NULL,
 resource VARCHAR(30) NOT NULL,
 source_id BIGINT UNSIGNED NOT NULL,
 document_id BIGINT UNSIGNED NOT NULL,
 UNIQUE KEY uq_financial_source(agency_id,resource,source_id),
 FOREIGN KEY(agency_id) REFERENCES agencies(id),
 FOREIGN KEY(document_id) REFERENCES accounting_documents(id)
) ENGINE=InnoDB;
