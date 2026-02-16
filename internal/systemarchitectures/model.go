package systemarchitectures

import "context"

// SystemArchitecturePopularity represents the popularity statistics for a system architecture.
type SystemArchitecturePopularity struct {
	Name       string  `json:"name"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

func (s SystemArchitecturePopularity) GetName() string        { return s.Name }
func (s SystemArchitecturePopularity) GetStartMonth() int     { return s.StartMonth }
func (s SystemArchitecturePopularity) GetPopularity() float64 { return s.Popularity }

// SystemArchitecturePopularityList represents a paginated list of system architecture popularities.
type SystemArchitecturePopularityList struct {
	Total                          int                            `json:"total"`
	Count                          int                            `json:"count"`
	SystemArchitecturePopularities []SystemArchitecturePopularity `json:"systemArchitecturePopularities"`
	Limit                          int                            `json:"limit"`
	Offset                         int                            `json:"offset"`
	Query                          *string                        `json:"query"`
}

// Repository defines the interface for system architecture data access.
type Repository interface {
	FindByName(ctx context.Context, name string, startMonth, endMonth int) (*SystemArchitecturePopularity, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error)
	FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error)
}

func newItem(identifier string, samples, count int, popularity float64, startMonth, endMonth int) SystemArchitecturePopularity {
	return SystemArchitecturePopularity{
		Name: identifier, Samples: samples, Count: count,
		Popularity: popularity, StartMonth: startMonth, EndMonth: endMonth,
	}
}

func newList(total, count int, items []SystemArchitecturePopularity, limit, offset int, query *string) SystemArchitecturePopularityList {
	return SystemArchitecturePopularityList{
		Total: total, Count: count, SystemArchitecturePopularities: items,
		Limit: limit, Offset: offset, Query: query,
	}
}
