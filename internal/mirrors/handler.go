package mirrors

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

// Handler handles HTTP requests for mirror endpoints.
type Handler struct {
	repo Repository
}

// NewHandler creates a new Handler with the given repository.
func NewHandler(repo Repository) *Handler {
	return &Handler{repo: repo}
}

// HandleGet handles GET /api/mirrors/{url}
func (h *Handler) HandleGet(w http.ResponseWriter, r *http.Request) {
	url := r.PathValue("url")
	if url == "" {
		http.Error(w, "mirror url required", http.StatusBadRequest)
		return
	}

	startMonth, endMonth := parseMonthRange(r)

	mirror, err := h.repo.FindByURL(r.Context(), url, startMonth, endMonth)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	writeJSON(w, mirror)
}

// HandleList handles GET /api/mirrors
func (h *Handler) HandleList(w http.ResponseWriter, r *http.Request) {
	startMonth, endMonth := parseMonthRange(r)
	limit := parseIntParam(r, "limit", defaultLimit)
	offset := parseIntParam(r, "offset", defaultOffset)
	query := r.URL.Query().Get("query")

	if limit > maxLimit {
		limit = maxLimit
	}
	if limit < 1 {
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

// HandleSeries handles GET /api/mirrors/{url}/series
func (h *Handler) HandleSeries(w http.ResponseWriter, r *http.Request) {
	url := r.PathValue("url")
	if url == "" {
		http.Error(w, "mirror url required", http.StatusBadRequest)
		return
	}

	startMonth, endMonth := parseMonthRange(r)
	limit := parseIntParam(r, "limit", defaultLimit)
	offset := parseIntParam(r, "offset", defaultOffset)

	if limit > maxLimit {
		limit = maxLimit
	}
	if limit < 1 {
		limit = 1
	}
	if offset < 0 {
		offset = 0
	}

	list, err := h.repo.FindSeriesByURL(r.Context(), url, startMonth, endMonth, limit, offset)
	if err != nil {
		http.Error(w, "internal server error", http.StatusInternalServerError)
		return
	}

	writeJSON(w, list)
}

// RegisterRoutes registers the mirror routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/mirrors", h.HandleList)
	mux.HandleFunc("GET /api/mirrors/{url}", h.HandleGet)
	mux.HandleFunc("GET /api/mirrors/{url}/series", h.HandleSeries)
}

func parseMonthRange(r *http.Request) (startMonth, endMonth int) {
	now := time.Now()
	currentMonth := now.Year()*monthMultiplier + int(now.Month())

	startMonth = parseIntParam(r, "startMonth", currentMonth)
	endMonth = parseIntParam(r, "endMonth", currentMonth)

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
