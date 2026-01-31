package web

import (
	"context"
	"encoding/json"
	"errors"
	"log/slog"
	"net/http"
	"os"
	"os/signal"
	"syscall"
	"time"
)

const (
	defaultReadTimeout     = 5 * time.Second
	defaultWriteTimeout    = 10 * time.Second
	defaultIdleTimeout     = 120 * time.Second
	defaultShutdownTimeout = 30 * time.Second
)

// Server wraps http.Server with graceful shutdown.
type Server struct {
	httpServer      *http.Server
	shutdownTimeout time.Duration
}

// NewServer creates a new Server with sensible defaults.
func NewServer(addr string, handler http.Handler) *Server {
	return &Server{
		httpServer: &http.Server{
			Addr:         addr,
			Handler:      handler,
			ReadTimeout:  defaultReadTimeout,
			WriteTimeout: defaultWriteTimeout,
			IdleTimeout:  defaultIdleTimeout,
		},
		shutdownTimeout: defaultShutdownTimeout,
	}
}

// ListenAndServe starts the server and blocks until shutdown signal is received.
func (s *Server) ListenAndServe() error {
	// Channel to receive shutdown signals
	stop := make(chan os.Signal, 1)
	signal.Notify(stop, os.Interrupt, syscall.SIGTERM)

	// Channel to receive server errors
	errCh := make(chan error, 1)

	go func() {
		slog.Info("server starting", "addr", s.httpServer.Addr)
		if err := s.httpServer.ListenAndServe(); err != nil && !errors.Is(err, http.ErrServerClosed) {
			errCh <- err
		}
	}()

	// Wait for shutdown signal or server error
	select {
	case err := <-errCh:
		return err
	case sig := <-stop:
		slog.Info("shutdown signal received", "signal", sig)
	}

	// Graceful shutdown
	ctx, cancel := context.WithTimeout(context.Background(), s.shutdownTimeout)
	defer cancel()

	slog.Info("shutting down server")
	if err := s.httpServer.Shutdown(ctx); err != nil {
		return err
	}

	slog.Info("server stopped")
	return nil
}

// WriteJSON writes a JSON response with the given status code.
func WriteJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json")
	w.WriteHeader(status)
	if err := json.NewEncoder(w).Encode(v); err != nil {
		slog.Error("failed to encode JSON response", "error", err)
	}
}
