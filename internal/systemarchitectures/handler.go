package systemarchitectures

import (
	"context"
	"database/sql"
	"net/http"

	"pkgstatsd/internal/popularity"
)

type SQLiteRepository struct {
	*popularity.Repository[SystemArchitecturePopularity, SystemArchitecturePopularityList]
}

func NewSQLiteRepository(db *sql.DB) *SQLiteRepository {
	return &SQLiteRepository{
		Repository: popularity.NewRepository(db, popularity.Config{
			Table:         "system_architecture",
			Column:        "name",
			QueryContains: true,
		}, newItem, newList),
	}
}

func (r *SQLiteRepository) FindByName(ctx context.Context, name string, startMonth, endMonth int) (*SystemArchitecturePopularity, error) {
	return r.FindByIdentifier(ctx, name, startMonth, endMonth)
}

func (r *SQLiteRepository) FindSeriesByName(ctx context.Context, name string, startMonth, endMonth, limit, offset int) (*SystemArchitecturePopularityList, error) {
	return r.FindSeries(ctx, name, startMonth, endMonth, limit, offset)
}

type Handler struct {
	pop *popularity.Handler[SystemArchitecturePopularity, SystemArchitecturePopularityList]
}

func NewHandler(repo *SQLiteRepository) *Handler {
	return &Handler{
		pop: popularity.NewHandler[SystemArchitecturePopularity, SystemArchitecturePopularityList](
			repo, "/api/system-architectures", "name", "architecture name required",
		),
	}
}

func newHandlerFromQuerier(q popularity.Querier[SystemArchitecturePopularity, SystemArchitecturePopularityList]) *Handler {
	return &Handler{
		pop: popularity.NewHandler[SystemArchitecturePopularity, SystemArchitecturePopularityList](
			q, "/api/system-architectures", "name", "architecture name required",
		),
	}
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	h.pop.RegisterRoutes(mux)
}
