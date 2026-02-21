// Command migrate-data exports data from MariaDB and imports it into SQLite.
//
// Usage:
//
//	migrate-data -mariadb "user:pass@tcp(host:3306)/dbname" -sqlite "./pkgstats.db"
package main

import (
	"context"
	"database/sql"
	"flag"
	"fmt"
	"log"
	"os"
	"time"

	_ "github.com/go-sql-driver/mysql"
	_ "modernc.org/sqlite"

	"pkgstatsd/internal/database"
)

func main() {
	mariadbDSN := flag.String("mariadb", "", "MariaDB connection string (user:pass@tcp(host:3306)/dbname)")
	sqlitePath := flag.String("sqlite", "", "SQLite database path")
	flag.Parse()

	if *mariadbDSN == "" {
		fmt.Fprintln(os.Stderr, "Error: -mariadb flag is required")
		flag.Usage()
		os.Exit(1)
	}

	if err := run(*mariadbDSN, *sqlitePath); err != nil {
		log.Fatal(err)
	}
}

func run(mariadbDSN, sqlitePath string) error {
	// Fail early if SQLite file already exists to avoid duplicate key errors
	if _, err := os.Stat(sqlitePath); err == nil {
		return fmt.Errorf("sqlite file %s already exists, remove it first", sqlitePath)
	}

	// Connect to MariaDB
	log.Println("Connecting to MariaDB...")
	mariadb, err := sql.Open("mysql", mariadbDSN)
	if err != nil {
		return fmt.Errorf("open mariadb: %w", err)
	}
	defer func() { _ = mariadb.Close() }()

	ctx := context.Background()
	if err := mariadb.PingContext(ctx); err != nil {
		return fmt.Errorf("ping mariadb: %w", err)
	}
	log.Println("Connected to MariaDB")

	// Initialize SQLite (creates schema via migrations)
	log.Printf("Initializing SQLite database at %s...\n", sqlitePath)
	sqlite, err := database.New(sqlitePath)
	if err != nil {
		return fmt.Errorf("init sqlite: %w", err)
	}
	defer func() { _ = sqlite.Close() }()
	log.Println("SQLite initialized")

	return migrate(mariadb, sqlite)
}

func migrate(src, dst *sql.DB) error {
	ctx := context.Background()

	// Migrate each table
	tables := []struct {
		name    string
		columns string
	}{
		{"package", "name, month, count"},
		{"country", "code, month, count"},
		{"mirror", "url, month, count"},
		{"system_architecture", "name, month, count"},
		{"operating_system_architecture", "name, month, count"},
		{"operating_system_id", "id, month, count"},
	}

	for _, table := range tables {
		if err := migrateTable(ctx, src, dst, table.name, table.columns); err != nil {
			return fmt.Errorf("migrate %s: %w", table.name, err)
		}
	}

	// Verify counts
	log.Println("\nVerifying row counts...")
	for _, table := range tables {
		if err := verifyCount(ctx, src, dst, table.name); err != nil {
			return fmt.Errorf("verify %s: %w", table.name, err)
		}
	}

	log.Println("\nMigration completed successfully!")
	return nil
}

func migrateTable(ctx context.Context, src, dst *sql.DB, table, columns string) error {
	start := time.Now()
	log.Printf("Migrating %s...\n", table)

	// Count rows in source
	var srcCount int
	if err := src.QueryRowContext(ctx, "SELECT COUNT(*) FROM "+table).Scan(&srcCount); err != nil {
		return fmt.Errorf("count source: %w", err)
	}
	log.Printf("  Source has %d rows\n", srcCount)

	if srcCount == 0 {
		log.Printf("  Skipping empty table\n")
		return nil
	}

	// Query all data from source
	// Table and column names are hardcoded, not user input
	query := fmt.Sprintf("SELECT %s FROM %s", columns, table) //nolint:gosec
	rows, err := src.QueryContext(ctx, query)
	if err != nil {
		return fmt.Errorf("query source: %w", err)
	}
	defer func() { _ = rows.Close() }()

	// Prepare insert statement (table/columns are hardcoded, not user input)
	placeholders := "?, ?, ?"
	insertQuery := fmt.Sprintf("INSERT INTO %s (%s) VALUES (%s)", table, columns, placeholders) //nolint:gosec

	// Begin transaction
	tx, err := dst.BeginTx(ctx, nil)
	if err != nil {
		return fmt.Errorf("begin tx: %w", err)
	}
	defer func() { _ = tx.Rollback() }()

	stmt, err := tx.PrepareContext(ctx, insertQuery)
	if err != nil {
		return fmt.Errorf("prepare insert: %w", err)
	}
	defer func() { _ = stmt.Close() }()

	// Insert rows in batches
	var inserted int
	for rows.Next() {
		var col1 string
		var col2, col3 int

		if err := rows.Scan(&col1, &col2, &col3); err != nil {
			return fmt.Errorf("scan row: %w", err)
		}

		if _, err := stmt.ExecContext(ctx, col1, col2, col3); err != nil {
			return fmt.Errorf("insert row: %w", err)
		}

		inserted++
		if inserted%100000 == 0 {
			log.Printf("  Inserted %d rows...\n", inserted)
		}
	}

	if err := rows.Err(); err != nil {
		return fmt.Errorf("iterate rows: %w", err)
	}

	if err := tx.Commit(); err != nil {
		return fmt.Errorf("commit: %w", err)
	}

	elapsed := time.Since(start)
	log.Printf("  Inserted %d rows in %s\n", inserted, elapsed.Round(time.Millisecond))
	return nil
}

func verifyCount(ctx context.Context, src, dst *sql.DB, table string) error {
	var srcCount, dstCount int

	if err := src.QueryRowContext(ctx, "SELECT COUNT(*) FROM "+table).Scan(&srcCount); err != nil {
		return fmt.Errorf("count source: %w", err)
	}

	if err := dst.QueryRowContext(ctx, "SELECT COUNT(*) FROM "+table).Scan(&dstCount); err != nil {
		return fmt.Errorf("count destination: %w", err)
	}

	if srcCount != dstCount {
		return fmt.Errorf("%s: count mismatch (source=%d, dest=%d)", table, srcCount, dstCount)
	}

	log.Printf("  %s: %d rows ✓\n", table, dstCount)
	return nil
}
