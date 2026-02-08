package operatingsystems

type OperatingSystemIdPopularity struct {
	ID         string  `json:"id"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

type OperatingSystemIdPopularityList struct {
	Total                         int                           `json:"total"`
	Count                         int                           `json:"count"`
	OperatingSystemIdPopularities []OperatingSystemIdPopularity `json:"operatingSystemIdPopularities"`
	Limit                         int                           `json:"limit"`
	Offset                        int                           `json:"offset"`
	Query                         *string                       `json:"query"`
}
