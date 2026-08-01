-- Short-lived fingerprints prevent accepted submissions from being counted
-- again when a client retries after losing the response.
CREATE TABLE submission_dedup (
    fingerprint BLOB PRIMARY KEY,
    expires_at INTEGER NOT NULL
);
CREATE INDEX idx_submission_dedup_expires_at ON submission_dedup(expires_at);
