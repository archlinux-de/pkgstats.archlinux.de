package operatingsystems

import "context"

// OperatingSystemIdPopularity represents the popularity statistics for an operating system ID.
type OperatingSystemIdPopularity struct {
	ID         string  `json:"id"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

// OperatingSystemIdPopularityList represents a paginated list of operating system ID popularities.
type OperatingSystemIdPopularityList struct {
	Total                         int                           `json:"total"`
	Count                         int                           `json:"count"`
	OperatingSystemIdPopularities []OperatingSystemIdPopularity `json:"operatingSystemIdPopularities"`
	Limit                         int                           `json:"limit"`
	Offset                        int                           `json:"offset"`
	Query                         *string                       `json:"query"`
}

// Repository defines the interface for operating system ID data access.
type Repository interface {
	FindByID(ctx context.Context, id string, startMonth, endMonth int) (*OperatingSystemIdPopularity, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error)
	FindSeriesByID(ctx context.Context, id string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error)
}

func newItem(identifier string, samples, count int, popularity float64, startMonth, endMonth int) OperatingSystemIdPopularity {
	return OperatingSystemIdPopularity{
		ID: identifier, Samples: samples, Count: count,
		Popularity: popularity, StartMonth: startMonth, EndMonth: endMonth,
	}
}

func newList(total, count int, items []OperatingSystemIdPopularity, limit, offset int, query *string) OperatingSystemIdPopularityList {
	return OperatingSystemIdPopularityList{
		Total: total, Count: count, OperatingSystemIdPopularities: items,
		Limit: limit, Offset: offset, Query: query,
	}
}
