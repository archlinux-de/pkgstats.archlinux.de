package apidoc

import "testing"

func TestPopularitySchemaDescriptions(t *testing.T) {
	spec := BuildSpec(true)

	tests := []struct {
		schema      string
		field       string
		description string
	}{
		{"PackagePopularity", "count", "Number of reports in the selected period that include the package."},
		{"PackagePopularity", "samples", "Estimated number of reports in the selected period."},
		{"CountryPopularity", "count", "Number of reports in the selected period that record this value."},
		{"CountryPopularity", "samples", "Number of reports in the selected period with a recorded value for this metric."},
		{"PackagePopularity", "popularity", "Percentage calculated as count / samples × 100, rounded to two decimal places."},
		{"PackagePopularityList", "packagePopularities", "Matching popularity records."},
		{"PackagePopularityList", "total", "Total number of matching records."},
	}

	for _, tt := range tests {
		t.Run(tt.schema+"_"+tt.field, func(t *testing.T) {
			schema := spec.Components.Schemas[tt.schema]
			if schema == nil {
				t.Fatalf("schema %q not found", tt.schema)
			}

			if got := schema.Properties[tt.field].Description; got != tt.description {
				t.Errorf("description = %q, want %q", got, tt.description)
			}
		})
	}
}
