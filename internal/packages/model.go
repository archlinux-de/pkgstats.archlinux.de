package packages

// PackagePopularity represents the popularity statistics for a package.
type PackagePopularity struct {
	Name       string  `json:"name"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

// PackagePopularityList represents a paginated list of package popularities.
type PackagePopularityList struct {
	Total               int                 `json:"total"`
	Count               int                 `json:"count"`
	PackagePopularities []PackagePopularity `json:"packagePopularities"`
	Limit               int                 `json:"limit"`
	Offset              int                 `json:"offset"`
	Query               *string             `json:"query"`
}
