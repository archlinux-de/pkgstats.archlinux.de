-- Operating system ID statistics (os-release ID)
CREATE TABLE operating_system_id (
    id TEXT NOT NULL,
    month INTEGER NOT NULL,
    count INTEGER NOT NULL DEFAULT 1,
    PRIMARY KEY (id, month)
);
CREATE INDEX idx_operating_system_id_month_id ON operating_system_id(month, id);
CREATE INDEX idx_operating_system_id_month_count ON operating_system_id(month, count DESC);
