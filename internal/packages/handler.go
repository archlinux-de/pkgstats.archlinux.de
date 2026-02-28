package packages

import (
	"net/http"

	"pkgstats.archlinux.de/internal/web"
)

type Handler struct {
	repo Repository
}

func NewHandler(repo Repository) *Handler {
	return &Handler{repo: repo}
}

func (h *Handler) HandleGet(w http.ResponseWriter, r *http.Request) {
	name := r.PathValue("name")
	if name == "" {
		http.Error(w, "package name required", http.StatusBadRequest)
		return
	}

	startMonth, endMonth := web.ParseMonthRange(r)

	pkg, err := h.repo.FindByName(r.Context(), name, startMonth, endMonth)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	web.WriteEntityJSON(w, pkg)
}

func (h *Handler) HandleList(w http.ResponseWriter, r *http.Request) {
	startMonth, endMonth := web.ParseMonthRange(r)
	limit := web.ParseIntParam(r, "limit", web.DefaultLimit)
	offset := web.ParseIntParam(r, "offset", 0)
	query := r.URL.Query().Get("query")

	limit, offset = web.NormalizePagination(limit, offset)

	list, err := h.repo.FindAll(r.Context(), query, startMonth, endMonth, limit, offset)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	web.WriteEntityJSON(w, list)
}

func (h *Handler) HandleSeries(w http.ResponseWriter, r *http.Request) {
	name := r.PathValue("name")
	if name == "" {
		http.Error(w, "package name required", http.StatusBadRequest)
		return
	}

	startMonth, endMonth := web.ParseMonthRange(r)
	limit := web.ParseIntParam(r, "limit", web.DefaultLimit)
	offset := web.ParseIntParam(r, "offset", 0)

	limit, offset = web.NormalizePagination(limit, offset)

	list, err := h.repo.FindSeriesByName(r.Context(), name, startMonth, endMonth, limit, offset)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	web.WriteEntityJSON(w, list)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/packages", h.HandleList)
	mux.HandleFunc("GET /api/packages/{name}", h.HandleGet)
	mux.HandleFunc("GET /api/packages/{name}/series", h.HandleSeries)
}
