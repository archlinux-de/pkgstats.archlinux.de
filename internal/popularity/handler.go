package popularity

import (
	"context"
	"net/http"

	"pkgstats.archlinux.de/internal/web"
)

// Querier defines the generic interface for popularity data access.
type Querier[T any, L any] interface {
	FindByIdentifier(ctx context.Context, identifier string, startMonth, endMonth int) (*T, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*L, error)
	FindSeries(ctx context.Context, identifier string, startMonth, endMonth, limit, offset int) (*L, error)
}

// Handler is a generic HTTP handler for popularity entities.
type Handler[T any, L any] struct {
	repo      Querier[T, L]
	basePath  string
	pathParam string
	errMsg    string
}

// NewHandler creates a new generic popularity handler.
func NewHandler[T any, L any](repo Querier[T, L], basePath, pathParam, errMsg string) *Handler[T, L] {
	return &Handler[T, L]{
		repo:      repo,
		basePath:  basePath,
		pathParam: pathParam,
		errMsg:    errMsg,
	}
}

// HandleGet handles GET /api/<entity>/{identifier}
func (h *Handler[T, L]) HandleGet(w http.ResponseWriter, r *http.Request) {
	identifier := r.PathValue(h.pathParam)
	if identifier == "" {
		http.Error(w, h.errMsg, http.StatusBadRequest)
		return
	}

	startMonth, endMonth := web.ParseMonthRange(r)

	item, err := h.repo.FindByIdentifier(r.Context(), identifier, startMonth, endMonth)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	web.WriteEntityJSON(w, item)
}

// HandleList handles GET /api/<entity>
func (h *Handler[T, L]) HandleList(w http.ResponseWriter, r *http.Request) {
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

// HandleSeries handles GET /api/<entity>/{identifier}/series
func (h *Handler[T, L]) HandleSeries(w http.ResponseWriter, r *http.Request) {
	identifier := r.PathValue(h.pathParam)
	if identifier == "" {
		http.Error(w, h.errMsg, http.StatusBadRequest)
		return
	}

	startMonth, endMonth := web.ParseMonthRange(r)
	limit := web.ParseIntParam(r, "limit", web.DefaultLimit)
	offset := web.ParseIntParam(r, "offset", 0)

	limit, offset = web.NormalizePagination(limit, offset)

	list, err := h.repo.FindSeries(r.Context(), identifier, startMonth, endMonth, limit, offset)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	web.WriteEntityJSON(w, list)
}

// RegisterRoutes registers the standard 3 routes for a popularity entity.
func (h *Handler[T, L]) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET "+h.basePath, h.HandleList)
	mux.HandleFunc("GET "+h.basePath+"/{"+h.pathParam+"}", h.HandleGet)
	mux.HandleFunc("GET "+h.basePath+"/{"+h.pathParam+"}/series", h.HandleSeries)
}
