package apidoc

import (
	_ "embed"
	"net/http"
)

//go:embed spec.json
var specJSON []byte

// Handler handles HTTP requests for the OpenAPI documentation.
type Handler struct{}

// NewHandler creates a new Handler.
func NewHandler() *Handler {
	return &Handler{}
}

// HandleDocJSON handles GET /api/doc.json
func (h *Handler) HandleDocJSON(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	_, _ = w.Write(specJSON)
}

// RegisterRoutes registers the API documentation routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/doc.json", h.HandleDocJSON)
}
