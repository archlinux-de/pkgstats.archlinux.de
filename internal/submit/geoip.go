package submit

import (
	"log/slog"
	"net/netip"

	"github.com/oschwald/maxminddb-golang/v2"
)

// GeoIPLookup defines the interface for GeoIP lookups.
type GeoIPLookup interface {
	GetCountryCode(ip netip.Addr) string
	Close() error
}

// MaxMindGeoIP implements GeoIPLookup using MaxMind database.
type MaxMindGeoIP struct {
	reader *maxminddb.Reader
}

// countryRecord represents the structure returned by MaxMind for country lookups.
type countryRecord struct {
	Country struct {
		ISOCode string `maxminddb:"iso_code"`
	} `maxminddb:"country"`
}

// NewMaxMindGeoIP creates a new MaxMindGeoIP from the given database path.
func NewMaxMindGeoIP(dbPath string) (*MaxMindGeoIP, error) {
	reader, err := maxminddb.Open(dbPath)
	if err != nil {
		return nil, err
	}
	return &MaxMindGeoIP{reader: reader}, nil
}

// GetCountryCode returns the ISO country code for the given IP address.
// Returns empty string if lookup fails.
func (g *MaxMindGeoIP) GetCountryCode(ip netip.Addr) string {
	var record countryRecord
	if err := g.reader.Lookup(ip).Decode(&record); err != nil {
		slog.Debug("geoip lookup failed", "ip", ip, "error", err)
		return ""
	}
	return record.Country.ISOCode
}

// Close closes the MaxMind database reader.
func (g *MaxMindGeoIP) Close() error {
	return g.reader.Close()
}

// NoopGeoIP is a GeoIP implementation that always returns empty.
// Used for testing or when GeoIP is not available.
type NoopGeoIP struct{}

// GetCountryCode always returns empty string.
func (NoopGeoIP) GetCountryCode(_ netip.Addr) string {
	return ""
}

// Close is a no-op.
func (NoopGeoIP) Close() error {
	return nil
}
