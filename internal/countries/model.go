package countries

type CountryPopularity struct {
	Code       string  `json:"code"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

type CountryPopularityList struct {
	Total               int                 `json:"total"`
	Count               int                 `json:"count"`
	CountryPopularities []CountryPopularity `json:"countryPopularities"`
	Limit               int                 `json:"limit"`
	Offset              int                 `json:"offset"`
	Query               string              `json:"query"`
}
