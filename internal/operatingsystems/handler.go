package operatingsystems

import (
	"context"
	"database/sql"
	"net/http"

	"pkgstatsd/internal/popularity"
)

type SQLiteRepository struct {
	*popularity.Repository[OperatingSystemIdPopularity, OperatingSystemIdPopularityList]
}

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

type Handler struct {
	pop *popularity.Handler[OperatingSystemIdPopularity, OperatingSystemIdPopularityList]
}

func NewHandler(repo *SQLiteRepository) *Handler {
	return &Handler{
		pop: popularity.NewHandler[OperatingSystemIdPopularity, OperatingSystemIdPopularityList](
			repo, "/api/operating-systems", "id", "operating system id required",
		),
	}
}

func newHandlerFromQuerier(q popularity.Querier[OperatingSystemIdPopularity, OperatingSystemIdPopularityList]) *Handler {
	return &Handler{
		pop: popularity.NewHandler[OperatingSystemIdPopularity, OperatingSystemIdPopularityList](
			q, "/api/operating-systems", "id", "operating system id required",
		),
	}
}

func (h *Handler) RegisterRoutes(mux *http.ServeMux) {
	h.pop.RegisterRoutes(mux)
}
