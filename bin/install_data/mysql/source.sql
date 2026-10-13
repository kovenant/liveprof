CREATE TABLE IF NOT EXISTS details (
  id INT UNSIGNED AUTO_INCREMENT NOT NULL,
  app VARCHAR(32) DEFAULT NULL,
  label VARCHAR(100) DEFAULT NULL,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
  perfdata MEDIUMTEXT DEFAULT NULL,
  INDEX details_timestamp_idx (timestamp),
  INDEX details_app_idx (app),
  INDEX details_label_idx (label),
  INDEX details_timestamp_label_idx (timestamp, label),
  PRIMARY KEY (id)
);
