package packages

import (
	"encoding/json"
	"net/http"
	"strconv"
	"time"
)

const (
	defaultLimit    = 100
	defaultOffset   = 0
	maxLimit        = 10000
	monthMultiplier = 100
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

	startMonth, endMonth := parseMonthRange(r)

	pkg, err := h.repo.FindByName(r.Context(), name, startMonth, endMonth)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	writeJSON(w, pkg)
}

// HandleList handles GET /api/packages
func (h *Handler) HandleList(w http.ResponseWriter, r *http.Request) {
	startMonth, endMonth := parseMonthRange(r)
	limit := parseIntParam(r, "limit", defaultLimit)
	offset := parseIntParam(r, "offset", defaultOffset)
	query := r.URL.Query().Get("query")

	if limit > maxLimit {
		limit = maxLimit
	}
	if limit == 0 {
		limit = maxLimit
	} else if limit < 1 {
		limit = 1
	}
	if offset < 0 {
		offset = 0
	}

	list, err := h.repo.FindAll(r.Context(), query, startMonth, endMonth, limit, offset)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	writeJSON(w, list)
}

// HandleSeries handles GET /api/packages/{name}/series
func (h *Handler) HandleSeries(w http.ResponseWriter, r *http.Request) {
	name := r.PathValue("name")
	if name == "" {
		http.Error(w, "package name required", http.StatusBadRequest)
		return
	}

	startMonth, endMonth := parseMonthRange(r)
	limit := parseIntParam(r, "limit", defaultLimit)
	offset := parseIntParam(r, "offset", defaultOffset)

	if limit > maxLimit {
		limit = maxLimit
	}
	if limit == 0 {
		limit = maxLimit
	} else if limit < 1 {
		limit = 1
	}
	if offset < 0 {
		offset = 0
	}

	list, err := h.repo.FindSeriesByName(r.Context(), name, startMonth, endMonth, limit, offset)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	writeJSON(w, list)
}

// RegisterRoutes registers the package routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/packages", h.HandleList)
	mux.HandleFunc("GET /api/packages/{name}", h.HandleGet)
	mux.HandleFunc("GET /api/packages/{name}/series", h.HandleSeries)
}

func parseMonthRange(r *http.Request) (startMonth, endMonth int) {
	now := time.Now()
	currentMonth := now.Year()*monthMultiplier + int(now.Month())

	startMonth = parseIntParam(r, "startMonth", currentMonth)
	endMonth = parseIntParam(r, "endMonth", currentMonth)

	// Treat 0 as "no constraint" to match PHP behavior where
	// startMonth=0/endMonth=0 means "no date filter"
	if endMonth <= 0 {
		endMonth = 999912
	}

	// Ensure valid range
	if startMonth > endMonth {
		startMonth, endMonth = endMonth, startMonth
	}

	return startMonth, endMonth
}

func parseIntParam(r *http.Request, key string, defaultValue int) int {
	s := r.URL.Query().Get(key)
	if s == "" {
		return defaultValue
	}
	v, err := strconv.Atoi(s)
	if err != nil {
		return defaultValue
	}
	return v
}

func writeJSON(w http.ResponseWriter, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.Header().Set("Access-Control-Allow-Origin", "*")
	if err := json.NewEncoder(w).Encode(v); err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
	}
}
