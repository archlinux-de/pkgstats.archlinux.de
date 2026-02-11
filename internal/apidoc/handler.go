package apidoc

import (
	_ "embed"
	"net/http"
)

//go:embed spec.json
var specJSON []byte

type Handler struct{}

// NewHandler creates a new Handler.
func NewHandler() *Handler {
	return &Handler{}
}

func (h *Handler) HandleDocJSON(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Content-Type", "application/json")
	_, _ = w.Write(specJSON)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/doc.json", h.HandleDocJSON)
}
