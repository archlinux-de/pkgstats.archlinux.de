package packages

type PackagePopularity struct {
	Name       string  `json:"name"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

type PackagePopularityList struct {
	Total               int                 `json:"total"`
	Count               int                 `json:"count"`
	PackagePopularities []PackagePopularity `json:"packagePopularities"`
	Limit               int                 `json:"limit"`
	Offset              int                 `json:"offset"`
	Query               string              `json:"query"`
}
