package chartdata

import (
	"testing"
)

type testPop struct {
	name       string
	month      int
	popularity float64
}

func (p testPop) GetName() string        { return p.name }
func (p testPop) GetStartMonth() int     { return p.month }
func (p testPop) GetPopularity() float64 { return p.popularity }

func TestBuild(t *testing.T) {
	tests := []struct {
		name         string
		input        []testPop
		wantLabels   []int
		wantDatasets int
	}{
		{
			"empty",
			[]testPop{},
			[]int{},
			0,
		},
		{
			"single month",
			[]testPop{{"a", 202501, 10.0}},
			[]int{202501},
			1,
		},
		{
			"multi month with gap",
			[]testPop{
				{"a", 202501, 10.0},
				{"a", 202503, 20.0},
			},
			[]int{202501, 202502, 202503},
			1,
		},
		{
			"multi entity sorting",
			[]testPop{
				{"a", 202501, 10.0},
				{"b", 202501, 20.0},
			},
			[]int{202501},
			2,
		},
	}

	for _, tt := range tests {
		t.Run(tt.name, func(t *testing.T) {
			got := Build(tt.input)
			if len(got.Labels) != len(tt.wantLabels) {
				t.Errorf("got %d labels, want %d", len(got.Labels), len(tt.wantLabels))
			}
			if len(got.Datasets) != tt.wantDatasets {
				t.Errorf("got %d datasets, want %d", len(got.Datasets), tt.wantDatasets)
			}
		})
	}
}

func TestNextMonth(t *testing.T) {
	tests := []struct {
		in   int
		want int
	}{
		{202401, 202402},
		{202412, 202501},
	}

	for _, tt := range tests {
		got := nextMonth(tt.in)
		if got != tt.want {
			t.Errorf("nextMonth(%d) = %d, want %d", tt.in, got, tt.want)
		}
	}
}
