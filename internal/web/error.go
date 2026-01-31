package web

import (
	"encoding/json"
	"net/http"
	"strconv"
)

// ProblemDetails represents an RFC 7807 Problem Details response.
type ProblemDetails struct {
	Type   string `json:"type"`
	Title  string `json:"title"`
	Status int    `json:"status"`
	Detail string `json:"detail,omitempty"`
}

// WriteError writes an RFC 7807 Problem Details error response.
func WriteError(w http.ResponseWriter, status int, detail string) {
	problem := ProblemDetails{
		Type:   "https://tools.ietf.org/html/rfc2616#section-10",
		Title:  http.StatusText(status),
		Status: status,
		Detail: detail,
	}

	w.Header().Set("Content-Type", "application/problem+json")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(problem)
}

// NotFound writes a 404 Not Found error response.
func NotFound(w http.ResponseWriter, detail string) {
	WriteError(w, http.StatusNotFound, detail)
}

// BadRequest writes a 400 Bad Request error response.
func BadRequest(w http.ResponseWriter, detail string) {
	WriteError(w, http.StatusBadRequest, detail)
}

// InternalServerError writes a 500 Internal Server Error response.
func InternalServerError(w http.ResponseWriter, detail string) {
	WriteError(w, http.StatusInternalServerError, detail)
}

// TooManyRequests writes a 429 Too Many Requests error response with Retry-After header.
func TooManyRequests(w http.ResponseWriter, detail string, retryAfterSeconds int) {
	w.Header().Set("Retry-After", strconv.Itoa(retryAfterSeconds))
	WriteError(w, http.StatusTooManyRequests, detail)
}
