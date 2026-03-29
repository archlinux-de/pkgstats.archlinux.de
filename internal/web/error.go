package web

import (
	"context"
	"encoding/json"
	"errors"
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

// ServerError logs the error and writes a 500 response, unless the error
// is due to a canceled context (client disconnect), in which case it's a no-op.
func ServerError(w http.ResponseWriter, msg string, err error) {
	if errors.Is(err, context.Canceled) {
		return
	}
	slog.Error(msg, "error", err)
	InternalServerError(w, "internal server error")
}

// IsClientDisconnect reports whether the error is due to a canceled context.
func IsClientDisconnect(err error) bool {
	return errors.Is(err, context.Canceled)
}

func TooManyRequests(w http.ResponseWriter, detail string, retryAfterSeconds int) {
	w.Header().Set("Retry-After", strconv.Itoa(retryAfterSeconds))
	WriteError(w, http.StatusTooManyRequests, detail)
}
