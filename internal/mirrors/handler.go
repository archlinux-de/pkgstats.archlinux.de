package mirrors

import (
	"context"
	"database/sql"
	"net/http"

	"pkgstatsd/internal/popularity"
)

// SQLiteRepository wraps the generic popularity repository.
type SQLiteRepository struct {
	*popularity.Repository[MirrorPopularity, MirrorPopularityList]
}

// NewSQLiteRepository creates a new SQLiteRepository.
func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{
		Repository: popularity.NewRepository(db, popularity.Config{
			Table:         "mirror",
			Column:        "url",
			QueryContains: true,
		}, newItem, newList),
	}
}

func (r *SQLiteRepository) FindByURL(ctx context.Context, url string, startMonth, endMonth int) (*MirrorPopularity, error) {
	return r.FindByIdentifier(ctx, url, startMonth, endMonth)
}

func (r *SQLiteRepository) FindSeriesByURL(ctx context.Context, url string, startMonth, endMonth, limit, offset int) (*MirrorPopularityList, error) {
	return r.FindSeries(ctx, url, startMonth, endMonth, limit, offset)
}

// Handler handles HTTP requests for mirror endpoints.
type Handler struct {
	pop *popularity.Handler[MirrorPopularity, MirrorPopularityList]
}

// NewHandler creates a new Handler from a database connection.
func NewHandler(db *sql.DB) *Handler {
	repo := popularity.NewRepository(db, popularity.Config{
		Table:         "mirror",
		Column:        "url",
		QueryContains: true,
	}, newItem, newList)

	return &Handler{
		pop: popularity.NewHandler[MirrorPopularity, MirrorPopularityList](
			repo, "/api/mirrors", "url", "mirror url required",
		),
	}
}

// newHandlerFromQuerier creates a Handler from a Querier (for testing).
func newHandlerFromQuerier(q popularity.Querier[MirrorPopularity, MirrorPopularityList]) *Handler {
	return &Handler{
		pop: popularity.NewHandler[MirrorPopularity, MirrorPopularityList](
			q, "/api/mirrors", "url", "mirror url required",
		),
	}
}

// RegisterRoutes registers the mirror routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	h.pop.RegisterRoutes(mux)
}
