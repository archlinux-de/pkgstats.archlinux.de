package popularity

import (
	"context"
	"net/http"

	"pkgstatsd/internal/web"
)

type Querier[T any, L any] interface {
	FindByIdentifier(ctx context.Context, identifier string, startMonth, endMonth int) (*T, error)
	FindAll(ctx context.Context, query string, startMonth, endMonth, limit, offset int) (*L, error)
	FindSeries(ctx context.Context, identifier string, startMonth, endMonth, limit, offset int) (*L, error)
}

type Handler[T any, L any] struct {
	repo      Querier[T, L]
	basePath  string
	pathParam string
	errMsg    string
}

func NewHandler[T, L any](repo Querier[T, L], basePath, pathParam, errMsg string) *Handler[T, L] {
	return &Handler[T, L]{
		repo:      repo,
		basePath:  basePath,
		pathParam: pathParam,
		errMsg:    errMsg,
	}
}

func (h *Handler[T, L]) HandleGet(w http.ResponseWriter, r *http.Request) {
	identifier := r.PathValue(h.pathParam)
	if identifier == "" {
		web.BadRequest(w, h.errMsg)
		return
	}

	startMonth, endMonth, err := web.ParseMonthRange(r)
	if err != nil {
		web.BadRequest(w, err.Error())
		return
	}

	item, err := h.repo.FindByIdentifier(r.Context(), identifier, startMonth, endMonth)
	if err != nil {
		web.ServerError(w, "failed to find item", err)
		return
	}

	web.WriteEntityJSON(w, item)
}

func (h *Handler[T, L]) HandleList(w http.ResponseWriter, r *http.Request) {
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
		web.ServerError(w, "failed to list items", err)
		return
	}

	web.WriteEntityJSON(w, list)
}

func (h *Handler[T, L]) HandleSeries(w http.ResponseWriter, r *http.Request) {
	identifier := r.PathValue(h.pathParam)
	if identifier == "" {
		web.BadRequest(w, h.errMsg)
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

	list, err := h.repo.FindSeries(r.Context(), identifier, startMonth, endMonth, limit, offset)
	if err != nil {
		web.ServerError(w, "failed to find item series", err)
		return
	}

	web.WriteEntityJSON(w, list)
}

func (h *Handler[T, L]) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET "+h.basePath, h.HandleList)
	mux.HandleFunc("GET "+h.basePath+"/{"+h.pathParam+"}", h.HandleGet)
	mux.HandleFunc("GET "+h.basePath+"/{"+h.pathParam+"}/series", h.HandleSeries)
}
