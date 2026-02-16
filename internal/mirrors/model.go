package mirrors

import "context"

// MirrorPopularity represents the popularity statistics for a mirror.
type MirrorPopularity struct {
	URL        string  `json:"url"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

// MirrorPopularityList represents a paginated list of mirror popularities.
type MirrorPopularityList struct {
	Total              int                `json:"total"`
	Count              int                `json:"count"`
	MirrorPopularities []MirrorPopularity `json:"mirrorPopularities"`
	Limit              int                `json:"limit"`
	Offset             int                `json:"offset"`
	Query              *string            `json:"query"`
}

// Repository defines the interface for mirror data access.
type Repository interface {
	FindByURL(ctx context.Context, url string, startMonth, endMonth int) (*MirrorPopularity, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*MirrorPopularityList, error)
	FindSeriesByURL(ctx context.Context, url string, startMonth, endMonth, limit, offset int) (*MirrorPopularityList, error)
}

func newItem(identifier string, samples, count int, popularity float64, startMonth, endMonth int) MirrorPopularity {
	return MirrorPopularity{
		URL: identifier, Samples: samples, Count: count,
		Popularity: popularity, StartMonth: startMonth, EndMonth: endMonth,
	}
}

func newList(total, count int, items []MirrorPopularity, limit, offset int, query *string) MirrorPopularityList {
	return MirrorPopularityList{
		Total: total, Count: count, MirrorPopularities: items,
		Limit: limit, Offset: offset, Query: query,
	}
}
