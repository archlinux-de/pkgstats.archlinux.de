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

-- Rate limiting table
CREATE TABLE rate_limit (
    key TEXT NOT NULL PRIMARY KEY,
    tokens INTEGER NOT NULL,
    last_update INTEGER NOT NULL
);
