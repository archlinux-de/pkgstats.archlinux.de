package operatingsystems

import (
	"context"
	"database/sql"
	"net/http"

	"pkgstats.archlinux.de/internal/popularity"
)

// SQLiteRepository wraps the generic popularity repository.
type SQLiteRepository struct {
	*popularity.Repository[OperatingSystemIdPopularity, OperatingSystemIdPopularityList]
}

// NewSQLiteRepository creates a new SQLiteRepository.
func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{
		Repository: popularity.NewRepository(db, popularity.Config{
			Table:  "operating_system_id",
			Column: "id",
		}, newItem, newList),
	}
}

func (r *SQLiteRepository) FindByID(ctx context.Context, id string, startMonth, endMonth int) (*OperatingSystemIdPopularity, error) {
	return r.FindByIdentifier(ctx, id, startMonth, endMonth)
}

func (r *SQLiteRepository) FindSeriesByID(ctx context.Context, id string, startMonth, endMonth, limit, offset int) (*OperatingSystemIdPopularityList, error) {
	return r.FindSeries(ctx, id, startMonth, endMonth, limit, offset)
}

// Handler handles HTTP requests for operating system ID endpoints.
type Handler struct {
	pop *popularity.Handler[OperatingSystemIdPopularity, OperatingSystemIdPopularityList]
}

// NewHandler creates a new Handler from a database connection.
func NewHandler(db *sql.DB) *Handler {
	repo := popularity.NewRepository(db, popularity.Config{
		Table:  "operating_system_id",
		Column: "id",
	}, newItem, newList)

	return &Handler{
		pop: popularity.NewHandler[OperatingSystemIdPopularity, OperatingSystemIdPopularityList](
			repo, "/api/operating-systems", "id", "operating system id required",
		),
	}
}

// newHandlerFromQuerier creates a Handler from a Querier (for testing).
func newHandlerFromQuerier(q popularity.Querier[OperatingSystemIdPopularity, OperatingSystemIdPopularityList]) *Handler {
	return &Handler{
		pop: popularity.NewHandler[OperatingSystemIdPopularity, OperatingSystemIdPopularityList](
			q, "/api/operating-systems", "id", "operating system id required",
		),
	}
}

// RegisterRoutes registers the operating system ID routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	h.pop.RegisterRoutes(mux)
}
