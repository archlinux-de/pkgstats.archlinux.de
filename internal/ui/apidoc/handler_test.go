package apidoc

import (
	"context"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"pkgstatsd/internal/apidoc"
	"pkgstatsd/internal/ui/layout"
)

func TestHandleAPIDocRendersSchemaDescriptions(t *testing.T) {
	handler := NewHandler(&layout.Manifest{}, apidoc.BuildSpec(false))
	rr := httptest.NewRecorder()
	req := httptest.NewRequest(http.MethodGet, "/api/doc", nil)

	handler.HandleAPIDoc(rr, req)

	if rr.Code != http.StatusOK {
		t.Fatalf("status = %d, want %d", rr.Code, http.StatusOK)
	}

	body := rr.Body.String()
	for _, text := range []string{
		`href="/methodology"`,
		"Description",
		"Estimated number of reports in the selected period.",
		"Percentage calculated as count / samples × 100, rounded to two decimal places.",
	} {
		if !strings.Contains(body, text) {
			t.Errorf("response does not contain %q", text)
		}
	}
}

func TestRenderSchemaDetailIncludesArrayItems(t *testing.T) {
	spec := apidoc.BuildSpec(false)
	var body strings.Builder
	err := renderSchemaDetail(
		&apidoc.Schema{Ref: "#/components/schemas/PackagePopularityList"},
		spec.Components,
	).Render(context.Background(), &body)
	if err != nil {
		t.Fatal(err)
	}

	for _, text := range []string{
		"Matching popularity records.",
		"PackagePopularity",
		"Estimated number of reports in the selected period.",
	} {
		if !strings.Contains(body.String(), text) {
			t.Errorf("response does not contain %q", text)
		}
	}
}
