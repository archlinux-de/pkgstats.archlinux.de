package submit

import (
	"log/slog"
	"net/netip"

	"github.com/oschwald/maxminddb-golang/v2"
)

type GeoIPLookup interface {
	GetCountryCode(ip netip.Addr) string
	Close() error
}

type MaxMindGeoIP struct {
	reader *maxminddb.Reader
}

type countryRecord struct {
	Country struct {
		ISOCode string `maxminddb:"iso_code"`
	} `maxminddb:"country"`
}

func NewMaxMindGeoIP(dbPath string) (*MaxMindGeoIP, error) {
	reader, err := maxminddb.Open(dbPath)
	if err != nil {
		return nil, err
	}
	return &MaxMindGeoIP{reader: reader}, nil
}

func (g *MaxMindGeoIP) GetCountryCode(ip netip.Addr) string {
	var record countryRecord
	if err := g.reader.Lookup(ip).Decode(&record); err != nil {
		slog.Warn("geoip lookup failed", "ip", ip, "error", err)
		return ""
	}
	return record.Country.ISOCode
}

func (g *MaxMindGeoIP) Close() error {
	return g.reader.Close()
}
