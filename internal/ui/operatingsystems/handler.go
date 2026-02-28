package operatingsystems

import (
	"log/slog"
	"net/http"

	"pkgstatsd/internal/chartdata"
	"pkgstatsd/internal/operatingsystems"
	"pkgstatsd/internal/ui/layout"
	"pkgstatsd/internal/web"
)

const (
	startMonth = 202602
	topLimit   = 10
)

type Handler struct {
	repo     operatingsystems.Repository
	manifest *layout.Manifest
}

func NewHandler(repo operatingsystems.Repository, manifest *layout.Manifest) *Handler {
	return &Handler{repo: repo, manifest: manifest}
}

func (h *Handler) HandleCompare(w http.ResponseWriter, r *http.Request) {
	endMonth := web.GetLastCompleteMonth()

	list, err := h.repo.FindAll(r.Context(), "", startMonth, endMonth, topLimit, 0)
	if err != nil {
		layout.ServerError(w, "failed to fetch operating systems", err)
		return
	}

	var allSeries []operatingsystems.OperatingSystemIdPopularity

	for _, osID := range list.OperatingSystemIdPopularities {
		series, err := h.repo.FindSeriesByID(r.Context(), osID.ID, startMonth, endMonth, layout.SeriesLimit, 0)
		if err != nil {
			// G706: Log injection via taint analysis (gosec)
			// slog structured logging safely handles the user-supplied value.
			//nolint:gosec
			slog.Error("failed to fetch operating system series", "error", err, "id", osID.ID)
			continue
		}

		allSeries = append(allSeries, series.OperatingSystemIdPopularities...)
	}

	data := chartdata.Build(allSeries)

	layout.Render(w, r,
		layout.Page{Title: "Compare Operating Systems", Path: "/compare/operating-systems", Manifest: h.manifest},
		CompareContent(data),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /compare/operating-systems", h.HandleCompare)
}
