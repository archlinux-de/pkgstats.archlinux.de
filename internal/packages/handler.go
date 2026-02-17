package packages

import (
	"net/http"

	"pkgstats.archlinux.de/internal/web"
)

// Handler handles HTTP requests for package endpoints.
type Handler struct {
	repo Repository
}

// NewHandler creates a new Handler with the given repository.
func NewHandler(repo Repository) *Handler {
	return &Handler{repo: repo}
}

// HandleGet handles GET /api/packages/{name}
func (h *Handler) HandleGet(w http.ResponseWriter, r *http.Request) {
	name := r.PathValue("name")
	if name == "" {
		http.Error(w, "package name required", http.StatusBadRequest)
		return
	}

	startMonth, endMonth, err := web.ParseMonthRange(r)
	if err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	pkg, err := h.repo.FindByName(r.Context(), name, startMonth, endMonth)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	web.WriteEntityJSON(w, pkg)
}

// HandleList handles GET /api/packages
func (h *Handler) HandleList(w http.ResponseWriter, r *http.Request) {
	startMonth, endMonth, err := web.ParseMonthRange(r)
	if err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	limit, offset, err := web.ParsePagination(r)
	if err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	query, err := web.ParseQuery(r)
	if err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	list, err := h.repo.FindAll(r.Context(), query, startMonth, endMonth, limit, offset)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	web.WriteEntityJSON(w, list)
}

// HandleSeries handles GET /api/packages/{name}/series
func (h *Handler) HandleSeries(w http.ResponseWriter, r *http.Request) {
	name := r.PathValue("name")
	if name == "" {
		http.Error(w, "package name required", http.StatusBadRequest)
		return
	}

	startMonth, endMonth, err := web.ParseMonthRange(r)
	if err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	limit, offset, err := web.ParsePagination(r)
	if err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	list, err := h.repo.FindSeriesByName(r.Context(), name, startMonth, endMonth, limit, offset)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	web.WriteEntityJSON(w, list)
}

// RegisterRoutes registers the package routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/packages", h.HandleList)
	mux.HandleFunc("GET /api/packages/{name}", h.HandleGet)
	mux.HandleFunc("GET /api/packages/{name}/series", h.HandleSeries)
}
