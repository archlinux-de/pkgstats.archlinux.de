package osarchitectures

import "context"

// OperatingSystemArchitecturePopularity represents the popularity statistics for an operating system architecture.
type OperatingSystemArchitecturePopularity struct {
	Name       string  `json:"name"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

// OperatingSystemArchitecturePopularityList represents a paginated list of operating system architecture popularities.
type OperatingSystemArchitecturePopularityList struct {
	Total                                   int                                     `json:"total"`
	Count                                   int                                     `json:"count"`
	OperatingSystemArchitecturePopularities []OperatingSystemArchitecturePopularity `json:"operatingSystemArchitecturePopularities"`
	Limit                                   int                                     `json:"limit"`
	Offset                                  int                                     `json:"offset"`
	Query                                   *string                                 `json:"query"`
}

// Repository defines the interface for operating system architecture data access.
type Repository interface {
	FindByName(ctx context.Context, name string, startMonth, endMonth int) (*OperatingSystemArchitecturePopularity, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*OperatingSystemArchitecturePopularityList, error)
	FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*OperatingSystemArchitecturePopularityList, error)
}

func newItem(identifier string, samples, count int, popularity float64, startMonth, endMonth int) OperatingSystemArchitecturePopularity {
	return OperatingSystemArchitecturePopularity{
		Name: identifier, Samples: samples, Count: count,
		Popularity: popularity, StartMonth: startMonth, EndMonth: endMonth,
	}
}

func newList(total, count int, items []OperatingSystemArchitecturePopularity, limit, offset int, query *string) OperatingSystemArchitecturePopularityList {
	return OperatingSystemArchitecturePopularityList{
		Total: total, Count: count, OperatingSystemArchitecturePopularities: items,
		Limit: limit, Offset: offset, Query: query,
	}
}
