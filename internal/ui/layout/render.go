package layout

import (
	"log/slog"
	"net/http"

	"github.com/a-h/templ"
)

func Render(w http.ResponseWriter, r *http.Request, page Page, content templ.Component) {
	if err := Base(page, content).Render(r.Context(), w); err != nil {
		slog.Error("failed to render page", "error", err)
	}
}

func ServerError(w http.ResponseWriter, msg string, err error) {
	slog.Error(msg, "error", err)
	http.Error(w, "internal server error", http.StatusInternalServerError)
}
