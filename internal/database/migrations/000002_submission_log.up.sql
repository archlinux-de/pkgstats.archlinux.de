-- Raw log of accepted submissions for abuse analysis and recovery.
-- Rows are pruned after the retention window.
CREATE TABLE submission_log (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    month INTEGER NOT NULL,
    timestamp INTEGER NOT NULL,
    ip TEXT NOT NULL,
    headers TEXT NOT NULL,
    payload TEXT NOT NULL,
    payload_hash TEXT NOT NULL,
    country TEXT NOT NULL
);
CREATE INDEX idx_submission_log_month ON submission_log(month);
