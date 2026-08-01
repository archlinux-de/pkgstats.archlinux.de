-- Package statistics
CREATE TABLE package (
    name TEXT NOT NULL,
    month INTEGER NOT NULL,
    count INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (name, month)
);
CREATE INDEX idx_package_month_name ON package(month, name);
CREATE INDEX idx_package_month_count ON package(month, count DESC);

-- Country statistics
CREATE TABLE country (
    code TEXT NOT NULL,
    month INTEGER NOT NULL,
    count INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (code, month)
);
CREATE INDEX idx_country_month_code ON country(month, code);
CREATE INDEX idx_country_month_count ON country(month, count DESC);

-- Mirror statistics
CREATE TABLE mirror (
    url TEXT NOT NULL,
    month INTEGER NOT NULL,
    count INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (url, month)
);
CREATE INDEX idx_mirror_month_url ON mirror(month, url);
CREATE INDEX idx_mirror_month_count ON mirror(month, count DESC);

-- System architecture statistics (CPU architecture)
CREATE TABLE system_architecture (
    name TEXT NOT NULL,
    month INTEGER NOT NULL,
    count INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (name, month)
);
CREATE INDEX idx_system_architecture_month_name ON system_architecture(month, name);
CREATE INDEX idx_system_architecture_month_count ON system_architecture(month, count DESC);

-- Operating system architecture statistics
CREATE TABLE operating_system_architecture (
    name TEXT NOT NULL,
    month INTEGER NOT NULL,
    count INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (name, month)
);
CREATE INDEX idx_os_architecture_month_name ON operating_system_architecture(month, name);
CREATE INDEX idx_os_architecture_month_count ON operating_system_architecture(month, count DESC);

-- Operating system ID statistics (os-release ID)
CREATE TABLE operating_system_id (
    id TEXT NOT NULL,
    month INTEGER NOT NULL,
    count INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (id, month)
);
CREATE INDEX idx_operating_system_id_month_id ON operating_system_id(month, id);
CREATE INDEX idx_operating_system_id_month_count ON operating_system_id(month, count DESC);

-- Rate limiting table (sliding window)
CREATE TABLE rate_limit (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    key TEXT NOT NULL,
    timestamp INTEGER NOT NULL
);
CREATE INDEX idx_rate_limit_key_timestamp ON rate_limit(key, timestamp);

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

-- Short-lived fingerprints prevent accepted submissions from being counted
-- again when a client retries after losing the response.
CREATE TABLE submission_dedup (
    fingerprint BLOB PRIMARY KEY,
    expires_at INTEGER NOT NULL
);
CREATE INDEX idx_submission_dedup_expires_at ON submission_dedup(expires_at);
