package systemarchitectures

import (
	"log/slog"
	"net/http"

	"pkgstatsd/internal/chartdata"
	"pkgstatsd/internal/systemarchitectures"
	"pkgstatsd/internal/ui/layout"
)

const (
	startMonthCurrent   = 202105
	endMonthLegacy      = 201812
	startMonthCommunity = 201712
)

type preset struct {
	Label         string
	Architectures []string
	StartMonth    int
	EndMonth      int
}

var presets = []preset{
	{
		Label:         "current",
		Architectures: []string{"aarch64", "armv5", "armv6", "armv7", "i686", "loong64", "riscv64", "x86_64", "x86_64_v2", "x86_64_v3", "x86_64_v4"},
		StartMonth:    startMonthCurrent,
		EndMonth:      layout.MaxEndMonth,
	},
	{
		Label:         "all",
		Architectures: []string{"aarch64", "armv5", "armv6", "armv7", "i686", "loong64", "riscv64", "x86_64", "x86_64_v2", "x86_64_v3", "x86_64_v4"},
		StartMonth:    0,
		EndMonth:      layout.MaxEndMonth,
	},
	{
		Label:         "i686-x86_64",
		Architectures: []string{"i686", "x86_64"},
		StartMonth:    0,
		EndMonth:      endMonthLegacy,
	},
	{
		Label:         "x86_64",
		Architectures: []string{"x86_64", "x86_64_v2", "x86_64_v3", "x86_64_v4"},
		StartMonth:    startMonthCurrent,
		EndMonth:      layout.MaxEndMonth,
	},
	{
		Label:         "community",
		Architectures: []string{"aarch64", "armv5", "armv6", "armv7", "i686", "loong64", "riscv64"},
		StartMonth:    startMonthCommunity,
		EndMonth:      layout.MaxEndMonth,
	},
}

type Handler struct {
	repo     systemarchitectures.Repository
	manifest *layout.Manifest
}

func NewHandler(repo systemarchitectures.Repository, manifest *layout.Manifest) *Handler {
	return &Handler{repo: repo, manifest: manifest}
}

func (h *Handler) HandleCompare(w http.ResponseWriter, r *http.Request) {
	presetName := r.PathValue("preset")
	if presetName == "" {
		presetName = "current"
	}

	p := findPreset(presetName)
	if p == nil {
		http.NotFound(w, r)
		return
	}

	var allSeries []systemarchitectures.SystemArchitecturePopularity

	for _, arch := range p.Architectures {
		list, err := h.repo.FindSeriesByName(r.Context(), arch, p.StartMonth, p.EndMonth, layout.SeriesLimit, 0)
		if err != nil {
			// G706: Log injection via taint analysis (gosec)
			// 'arch' is a hardcoded string from presets, not user input.
			//nolint:gosec
			slog.Error("failed to fetch architecture series", "error", err, "name", arch)
			continue
		}

		allSeries = append(allSeries, list.SystemArchitecturePopularities...)
	}

	data := chartdata.Build(allSeries)

	layout.Render(w, r,
		layout.Page{Title: "Compare System Architectures", Path: "/compare/system-architectures", Manifest: h.manifest},
		CompareContent(presets, p.Label, data),
	)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /compare/system-architectures", func(w http.ResponseWriter, r *http.Request) {
		http.Redirect(w, r, "/compare/system-architectures/current", http.StatusFound)
	})
	mux.HandleFunc("GET /compare/system-architectures/{preset}", h.HandleCompare)
}

func findPreset(name string) *preset {
	for i := range presets {
		if presets[i].Label == name {
			return &presets[i]
		}
	}

	return nil
}
