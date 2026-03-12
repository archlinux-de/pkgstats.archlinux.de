package apidoc

import (
	"encoding/json"
	"net/http"
)

type Handler struct {
	specJSON []byte
}

func NewHandler(includeInternal bool) *Handler {
	data, err := json.MarshalIndent(BuildSpec(includeInternal), "", "    ")
	if err != nil {
		panic("apidoc: failed to marshal OpenAPI spec: " + err.Error())
	}
	return &Handler{specJSON: data}
}

func (h *Handler) HandleDocJSON(w http.ResponseWriter, _ *http.Request) {
	w.Header().Set("Cache-Control", "public, max-age=86400")
	w.Header().Set("Content-Type", "application/json")
	_, _ = w.Write(h.specJSON)
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	mux.HandleFunc("GET /api/doc.json", h.HandleDocJSON)
}
