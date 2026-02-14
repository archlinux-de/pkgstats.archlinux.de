package chartdata

import (
	"sort"
)

const (
	monthsInYear = 12
	yearFactor   = 100
)

// Popularity is implemented by any entity that has time-series popularity data.
type Popularity interface {
	GetName() string
	GetStartMonth() int
	GetPopularity() float64
}

type Data struct {
	Labels   []int     `json:"labels"`
	Datasets []Dataset `json:"datasets"`
}

type Dataset struct {
	Label string     `json:"label"`
	Data  []*float64 `json:"data"`
}

// Build transforms popularity entries into a compact ChartJS-ready format.
// Labels are sorted months, datasets are sorted by last month's popularity descending.
// Null values represent missing months for a given entity.
func Build[T Popularity](popularities []T) Data {
	labelSet := make(map[int]struct{})
	seriesMap := make(map[string]map[int]float64)

	for _, p := range popularities {
		labelSet[p.GetStartMonth()] = struct{}{}

		m, ok := seriesMap[p.GetName()]
		if !ok {
			m = make(map[int]float64)
			seriesMap[p.GetName()] = m
		}

		m[p.GetStartMonth()] = p.GetPopularity()
	}

	if len(labelSet) == 0 {
		return Data{Labels: []int{}, Datasets: []Dataset{}}
	}

	// Find min/max months and generate continuous range
	var minMonth, maxMonth int
	for l := range labelSet {
		if minMonth == 0 || l < minMonth {
			minMonth = l
		}

		if l > maxMonth {
			maxMonth = l
		}
	}

	labels := monthRange(minMonth, maxMonth)

	type namedSeries struct {
		name   string
		series map[int]float64
	}

	sorted := make([]namedSeries, 0, len(seriesMap))
	for name, series := range seriesMap {
		sorted = append(sorted, namedSeries{name, series})
	}

	lastLabel := labels[len(labels)-1]
	sort.Slice(sorted, func(i, j int) bool {
		return sorted[i].series[lastLabel] > sorted[j].series[lastLabel]
	})

	datasets := make([]Dataset, len(sorted))
	for i, s := range sorted {
		data := make([]*float64, len(labels))
		for j, label := range labels {
			if v, ok := s.series[label]; ok {
				data[j] = &v
			}
		}

		datasets[i] = Dataset{Label: s.name, Data: data}
	}

	return Data{Labels: labels, Datasets: datasets}
}

// monthRange generates a continuous sequence of months in YYYYMM format.
func monthRange(from, to int) []int {
	var months []int
	for m := from; m <= to; m = nextMonth(m) {
		months = append(months, m)
	}

	return months
}

func nextMonth(m int) int {
	if m%yearFactor >= monthsInYear {
		return (m/yearFactor+1)*yearFactor + 1
	}

	return m + 1
}
