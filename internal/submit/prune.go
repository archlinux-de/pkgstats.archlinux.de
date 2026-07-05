package submit

import (
	"context"
	"fmt"
	"os"

	"pkgstatsd/internal/config"
	"pkgstatsd/internal/database"
)

// RunPruneLog executes the prune-submission-log subcommand. It deletes
// submission log entries older than the retention window and returns the
// process exit code. Meant to be run periodically by an external scheduler,
// off the request path.
func RunPruneLog(_ []string, cfg config.Config) int {
	db, err := database.New(cfg.Database)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error: %v\n", err)
		return 1
	}
	defer func() { _ = db.Close() }()

	deleted, err := NewRepository(db).PruneLog(context.Background())
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error: %v\n", err)
		return 1
	}

	fmt.Printf("Pruned %d expired submission log entries.\n", deleted)
	return 0
}
