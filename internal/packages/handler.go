package packages

import (
	"log/slog"
	"net/http"

	"pkgstatsd/internal/web"
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
		web.BadRequest(w, "package name required")
		return
	}

	startMonth, endMonth, err := web.ParseMonthRange(r)
	if err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	pkg, err := h.repo.FindByName(r.Context(), name, startMonth, endMonth)
	if err != nil {
		slog.Error("failed to find package", "error", err)
		web.InternalServerError(w, "internal server error")
		return
	}

	web.WriteEntityJSON(w, pkg)
}

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
		slog.Error("failed to list packages", "error", err)
		web.InternalServerError(w, "internal server error")
		return
	}

	web.WriteEntityJSON(w, list)
}

func (h *Handler) HandleSeries(w http.ResponseWriter, r *http.Request) {
	name := r.PathValue("name")
	if name == "" {
		web.BadRequest(w, "package name required")
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
		slog.Error("failed to find package series", "error", err)
		web.InternalServerError(w, "internal server error")
		return
	}

	web.WriteEntityJSON(w, list)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/packages", h.HandleList)
	mux.HandleFunc("GET /api/packages/{name}", h.HandleGet)
	mux.HandleFunc("GET /api/packages/{name}/series", h.HandleSeries)
}
