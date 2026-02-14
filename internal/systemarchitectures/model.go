package systemarchitectures

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

type SystemArchitecturePopularityList struct {
	Total                          int                            `json:"total"`
	Count                          int                            `json:"count"`
	SystemArchitecturePopularities []SystemArchitecturePopularity `json:"systemArchitecturePopularities"`
	Limit                          int                            `json:"limit"`
	Offset                         int                            `json:"offset"`
	Query                          *string                        `json:"query"`
}
