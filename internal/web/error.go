package web

import (
	"encoding/json"
	"log/slog"
	"net/http"
	"strconv"
)

type ProblemDetails struct {
	Type   string `json:"type"`
	Title  string `json:"title"`
	Status int    `json:"status"`
	Detail string `json:"detail,omitempty"`
}

func WriteError(w http.ResponseWriter, status int, detail string) {
	problem := ProblemDetails{
		Type:   "https://tools.ietf.org/html/rfc2616#section-10",
		Title:  http.StatusText(status),
		Status: status,
		Detail: detail,
	}

	w.Header().Set("Cache-Control", "no-store")
	w.Header().Set("Content-Type", "application/problem+json")
	w.WriteHeader(status)
	if err := json.NewEncoder(w).Encode(problem); err != nil {
		slog.Error("failed to encode error response", "error", err)
	}
}

func NotFound(w http.ResponseWriter, detail string) {
	WriteError(w, http.StatusNotFound, detail)
}

func BadRequest(w http.ResponseWriter, detail string) {
	WriteError(w, http.StatusBadRequest, detail)
}

func InternalServerError(w http.ResponseWriter, detail string) {
	WriteError(w, http.StatusInternalServerError, detail)
}

func TooManyRequests(w http.ResponseWriter, detail string, retryAfterSeconds int) {
	w.Header().Set("Retry-After", strconv.Itoa(retryAfterSeconds))
	WriteError(w, http.StatusTooManyRequests, detail)
}
