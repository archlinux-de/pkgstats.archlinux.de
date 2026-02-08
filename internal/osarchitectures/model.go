package osarchitectures

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
