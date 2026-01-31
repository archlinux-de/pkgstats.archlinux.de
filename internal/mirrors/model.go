package mirrors

type MirrorPopularity struct {
	URL        string  `json:"url"`
	Samples    int     `json:"samples"`
	Count      int     `json:"count"`
	Popularity float64 `json:"popularity"`
	StartMonth int     `json:"startMonth"`
	EndMonth   int     `json:"endMonth"`
}

type MirrorPopularityList struct {
	Total              int                `json:"total"`
	Count              int                `json:"count"`
	MirrorPopularities []MirrorPopularity `json:"mirrorPopularities"`
	Limit              int                `json:"limit"`
	Offset             int                `json:"offset"`
	Query              *string            `json:"query"`
}
