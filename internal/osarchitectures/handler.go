package osarchitectures

import (
	"context"
	"database/sql"
	"net/http"

	"pkgstatsd/internal/popularity"
)

type SQLiteRepository struct {
	*popularity.Repository[OperatingSystemArchitecturePopularity, OperatingSystemArchitecturePopularityList]
}

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

func newHandlerFromQuerier(q popularity.Querier[OperatingSystemArchitecturePopularity, OperatingSystemArchitecturePopularityList]) *Handler {
	return &Handler{
		pop: popularity.NewHandler[OperatingSystemArchitecturePopularity, OperatingSystemArchitecturePopularityList](
			q, "/api/operating-system-architectures", "name", "architecture name required",
		),
	}
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	h.pop.RegisterRoutes(mux)
}
