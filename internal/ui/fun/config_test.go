package fun

import (
	"slices"
	"testing"
)

func TestCategoriesAreSorted(t *testing.T) {
	if !slices.IsSortedFunc(Categories, func(a, b Category) int {
		if a.Name < b.Name {
			return -1
		}
		if a.Name > b.Name {
			return 1
		}
		return 0
	}) {
		t.Error("Categories must be sorted alphabetically by Name")
	}
}

func TestPackagesAreSorted(t *testing.T) {
	for _, cat := range Categories {
		if !slices.IsSorted(cat.Packages) {
			t.Errorf("Packages in category %q must be sorted alphabetically", cat.Name)
		}
	}
}
