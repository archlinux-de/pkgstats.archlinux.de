package osarchitectures

import (
	"context"
	"database/sql"
	"net/http"

	"pkgstats.archlinux.de/internal/popularity"
)

// SQLiteRepository wraps the generic popularity repository.
type SQLiteRepository struct {
	*popularity.Repository[OperatingSystemArchitecturePopularity, OperatingSystemArchitecturePopularityList]
}

// NewSQLiteRepository creates a new SQLiteRepository.
func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{
		Repository: popularity.NewRepository(db, popularity.Config{
			Table:  "operating_system_architecture",
			Column: "name",
		}, newItem, newList),
	}
}

func (r *SQLiteRepository) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*OperatingSystemArchitecturePopularity, error) {
	return r.FindByIdentifier(ctx, name, startMonth, endMonth)
}

func (r *SQLiteRepository) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*OperatingSystemArchitecturePopularityList, error) {
	return r.FindSeries(ctx, name, startMonth, endMonth, limit, offset)
}

// Handler handles HTTP requests for operating system architecture endpoints.
type Handler struct {
	pop *popularity.Handler[OperatingSystemArchitecturePopularity, OperatingSystemArchitecturePopularityList]
}

// NewHandler creates a new Handler from a database connection.
func NewHandler(db *sql.DB) *Handler {
	repo := popularity.NewRepository(db, popularity.Config{
		Table:  "operating_system_architecture",
		Column: "name",
	}, newItem, newList)

	return &Handler{
		pop: popularity.NewHandler[OperatingSystemArchitecturePopularity, OperatingSystemArchitecturePopularityList](
			repo, "/api/operating-system-architectures", "name", "architecture name required",
		),
	}
}

// newHandlerFromQuerier creates a Handler from a Querier (for testing).
func newHandlerFromQuerier(q popularity.Querier[OperatingSystemArchitecturePopularity, OperatingSystemArchitecturePopularityList]) *Handler {
	return &Handler{
		pop: popularity.NewHandler[OperatingSystemArchitecturePopularity, OperatingSystemArchitecturePopularityList](
			q, "/api/operating-system-architectures", "name", "architecture name required",
		),
	}
}

// RegisterRoutes registers the operating system architecture routes on the given mux.
func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	h.pop.RegisterRoutes(mux)
}
