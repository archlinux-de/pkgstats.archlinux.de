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

	result, err := NewRepository(db).ArchiveAndPruneLog(context.Background(), cfg.SubmissionLogArchiveDir)
	if err != nil {
		fmt.Fprintf(os.Stderr, "Error: %v\n", err)
		return 1
	}

	fmt.Printf("Archived %d months, pruned %d expired submission log entries, and removed %d expired archives.\n",
		result.ArchivedMonths, result.PrunedEntries, result.RemovedArchives)
	return 0
}
