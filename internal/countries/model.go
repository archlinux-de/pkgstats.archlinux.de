package countries

import "context"

// CountryPopularity represents the popularity statistics for a country.
type CountryPopularity struct {
	Code       string  `json:"code"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

// CountryPopularityList represents a paginated list of country popularities.
type CountryPopularityList struct {
	Total               int                 `json:"total"`
	Count               int                 `json:"count"`
	CountryPopularities []CountryPopularity `json:"countryPopularities"`
	Limit               int                 `json:"limit"`
	Offset              int                 `json:"offset"`
	Query               *string             `json:"query"`
}

// Repository defines the interface for country data access.
type Repository interface {
	FindByCode(ctx context.Context, code string, startMonth, endMonth int) (*CountryPopularity, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error)
	FindSeriesByCode(ctx context.Context, code string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error)
}

func newItem(identifier string, samples, count int, popularity float64, startMonth, endMonth int) CountryPopularity {
	return CountryPopularity{
		Code: identifier, Samples: samples, Count: count,
		Popularity: popularity, StartMonth: startMonth, EndMonth: endMonth,
	}
}

func newList(total, count int, items []CountryPopularity, limit, offset int, query *string) CountryPopularityList {
	return CountryPopularityList{
		Total: total, Count: count, CountryPopularities: items,
		Limit: limit, Offset: offset, Query: query,
	}
}
