package countries

import (
	"context"
	"database/sql"
	"net/http"

	"pkgstatsd/internal/popularity"
)

// SQLiteRepository wraps the generic popularity repository.
type SQLiteRepository struct {
	*popularity.Repository[CountryPopularity, CountryPopularityList]
}

// NewSQLiteRepository creates a new SQLiteRepository.
func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{
		Repository: popularity.NewRepository(db, popularity.Config{
			Table:  "country",
			Column: "code",
		}, newItem, newList),
	}
}

func (r *SQLiteRepository) FindByCode(ctx context.Context, code string, startMonth, endMonth int) (*CountryPopularity, error) {
	return r.FindByIdentifier(ctx, code, startMonth, endMonth)
}

func (r *SQLiteRepository) FindSeriesByCode(ctx context.Context, code string, startMonth, endMonth, limit, offset int) (*CountryPopularityList, error) {
	return r.FindSeries(ctx, code, startMonth, endMonth, limit, offset)
}

// Handler handles HTTP requests for country endpoints.
type Handler struct {
	pop *popularity.Handler[CountryPopularity, CountryPopularityList]
}

// NewHandler creates a new Handler from a database connection.
func NewHandler(db *sql.DB) *Handler {
	repo := popularity.NewRepository(db, popularity.Config{
		Table:  "country",
		Column: "code",
	}, newItem, newList)

	return &Handler{
		pop: popularity.NewHandler[CountryPopularity, CountryPopularityList](
			repo, "/api/countries", "code", "country code required",
		),
	}
}

// newHandlerFromQuerier creates a Handler from a Querier (for testing).
func newHandlerFromQuerier(q popularity.Querier[CountryPopularity, CountryPopularityList]) *Handler {
	return &Handler{
		pop: popularity.NewHandler[CountryPopularity, CountryPopularityList](
			q, "/api/countries", "code", "country code required",
		),
	}
}

// RegisterRoutes registers the country routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	h.pop.RegisterRoutes(mux)
}
