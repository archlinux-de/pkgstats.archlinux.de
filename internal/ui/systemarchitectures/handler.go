package systemarchitectures

import (
	"log/slog"
	"net/http"

	"pkgstats.archlinux.de/internal/chartdata"
	"pkgstats.archlinux.de/internal/systemarchitectures"
	"pkgstats.archlinux.de/internal/ui/layout"
)

const (
	seriesLimit         = 10000
	maxEndMonth         = 999912
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
		EndMonth:      maxEndMonth,
	},
	{
		Label:         "all",
		Architectures: []string{"aarch64", "armv5", "armv6", "armv7", "i686", "loong64", "riscv64", "x86_64", "x86_64_v2", "x86_64_v3", "x86_64_v4"},
		StartMonth:    0,
		EndMonth:      maxEndMonth,
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
		EndMonth:      maxEndMonth,
	},
	{
		Label:         "community",
		Architectures: []string{"aarch64", "armv5", "armv6", "armv7", "i686", "loong64", "riscv64"},
		StartMonth:    startMonthCommunity,
		EndMonth:      maxEndMonth,
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
		list, err := h.repo.FindSeriesByName(r.Context(), arch, p.StartMonth, p.EndMonth, seriesLimit, 0)
		if err != nil {
			slog.Error("failed to fetch architecture series", "error", err, "name", arch)
			continue
		}

		allSeries = append(allSeries, list.SystemArchitecturePopularities...)
	}

	data := chartdata.Build(allSeries)

	w.Header().Set("Cache-Control", "public, max-age=300")
	component := layout.Base(
		layout.Page{Title: "Compare System Architectures", Path: "/compare/system-architectures", Manifest: h.manifest},
		CompareContent(presets, p.Label, data),
	)
	_ = component.Render(r.Context(), w)
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
