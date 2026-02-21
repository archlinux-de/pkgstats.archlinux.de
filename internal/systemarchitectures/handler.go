package systemarchitectures

import (
	"context"
	"database/sql"
	"net/http"

	"pkgstatsd/internal/popularity"
)

// SQLiteRepository wraps the generic popularity repository.
type SQLiteRepository struct {
	*popularity.Repository[SystemArchitecturePopularity, SystemArchitecturePopularityList]
}

// NewSQLiteRepository creates a new SQLiteRepository.
func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{
		Repository: popularity.NewRepository(db, popularity.Config{
			Table:  "system_architecture",
			Column: "name",
		}, newItem, newList),
	}
}

func (r *SQLiteRepository) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*SystemArchitecturePopularity, error) {
	return r.FindByIdentifier(ctx, name, startMonth, endMonth)
}

func (r *SQLiteRepository) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error) {
	return r.FindSeries(ctx, name, startMonth, endMonth, limit, offset)
}

// Handler handles HTTP requests for system architecture endpoints.
type Handler struct {
	pop *popularity.Handler[SystemArchitecturePopularity, SystemArchitecturePopularityList]
}

// NewHandler creates a new Handler from a database connection.
func NewHandler(db *sql.DB) *Handler {
	repo := popularity.NewRepository(db, popularity.Config{
		Table:  "system_architecture",
		Column: "name",
	}, newItem, newList)

	return &Handler{
		pop: popularity.NewHandler[SystemArchitecturePopularity, SystemArchitecturePopularityList](
			repo, "/api/system-architectures", "name", "architecture name required",
		),
	}
}

// newHandlerFromQuerier creates a Handler from a Querier (for testing).
func newHandlerFromQuerier(q popularity.Querier[SystemArchitecturePopularity, SystemArchitecturePopularityList]) *Handler {
	return &Handler{
		pop: popularity.NewHandler[SystemArchitecturePopularity, SystemArchitecturePopularityList](
			q, "/api/system-architectures", "name", "architecture name required",
		),
	}
}

// RegisterRoutes registers the system architecture routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	h.pop.RegisterRoutes(mux)
}
