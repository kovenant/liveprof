CREATE TABLE IF NOT EXISTS details (
  id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
  app VARCHAR(32) DEFAULT NULL,
  label VARCHAR(100) DEFAULT NULL,
  timestamp DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL,
  perfdata CLOB DEFAULT NULL
);
CREATE INDEX IF NOT EXISTS details_timestamp_idx ON details (timestamp);
CREATE INDEX IF NOT EXISTS details_app_idx ON details (app);
CREATE INDEX IF NOT EXISTS details_label_idx ON details (label);
CREATE INDEX IF NOT EXISTS details_timestamp_label_idx ON details (timestamp, label);
