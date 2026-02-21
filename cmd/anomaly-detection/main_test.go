package main

import (
	"context"
	"testing"

	"pkgstatsd/internal/database"
)

func TestOffsetMonth(t *testing.T) {
	tests := []struct {
		in     int
		offset int
		want   int
	}{
		{202501, -1, 202412},
		{202501, 1, 202502},
		{202501, -12, 202401},
		{202501, -13, 202312},
		{202501, 12, 202601},
	}

	for _, tt := range tests {
		got := offsetMonth(tt.in, tt.offset)
		if got != tt.want {
			t.Errorf("offsetMonth(%d, %d) = %d, want %d", tt.in, tt.offset, got, tt.want)
		}
	}
}

func TestCalculateMedian(t *testing.T) {
	tests := []struct {
		in   []int
		want int
	}{
		{[]int{10, 20, 30}, 20},
		{[]int{10, 20, 30, 40}, 25},
		{[]int{100}, 100},
	}

	for _, tt := range tests {
		got := calculateMedian(tt.in)
		if got != tt.want {
			t.Errorf("calculateMedian(%v) = %d, want %d", tt.in, got, tt.want)
		}
	}
}

func TestFindBasePackageOutliers(t *testing.T) {
	baseCounts := map[string]int{
		"pkg1": 100,
		"pkg2": 110,
		"pkg3": 250, // Outlier
	}
	median := 110
	threshold := 150.0

	outliers := findBasePackageOutliers(baseCounts, median, threshold)

	if len(outliers) != 1 {
		t.Errorf("expected 1 outlier, got %d", len(outliers))
	}
	if outliers[0].Name != "pkg3" {
		t.Errorf("expected pkg3, got %s", outliers[0].Name)
	}
}

func TestAnomalyDetection_Integration(t *testing.T) {
	db, err := database.New(":memory:")
	if err != nil {
		t.Fatalf("failed to create database: %v", err)
	}
	defer func() { _ = db.Close() }()

	// Insert data for growth anomalies
	// Mirror baseline: 3 months of 100
	_, _ = db.Exec(`INSERT INTO mirror (url, month, count) VALUES
		('http://m1', 202410, 100), ('http://m1', 202411, 100), ('http://m1', 202412, 100),
		('http://m1', 202501, 1000)`) // 900% growth

	// Package spikes
	_, _ = db.Exec(`INSERT INTO package (name, month, count) VALUES ('new-spike', 202501, 2000)`)

	// Base package correlation
	_, _ = db.Exec(`INSERT INTO package (name, month, count) VALUES
		('pkgstats', 202501, 5000), ('pacman', 202501, 5000), ('linux', 202501, 5000)`)

	target := 202501
	baselineStart := 202407
	baselineEnd := 202412

	result, err := detect(context.Background(), db, target, baselineStart, baselineEnd, []string{"pkgstats", "pacman", "linux"})
	if err != nil {
		t.Fatalf("detect error: %v", err)
	}

	if len(result.MirrorAnomalies) == 0 {
		t.Error("expected mirror anomalies")
	}
	if len(result.NewPackageSpikes) == 0 {
		t.Error("expected new package spikes")
	}
}

func TestDetectionResult_IsHighConfidence(t *testing.T) {
	t.Run("extreme mirror growth", func(t *testing.T) {
		res := &DetectionResult{
			MirrorAnomalies: []GrowthAnomaly{{GrowthPercent: 1001.0}},
		}
		if !res.IsHighConfidence() {
			t.Error("expected high confidence for extreme growth")
		}
	})

	t.Run("mirror AND arch anomalies", func(t *testing.T) {
		res := &DetectionResult{
			MirrorAnomalies:     []GrowthAnomaly{{GrowthPercent: 400.0}},
			SystemArchAnomalies: []GrowthAnomaly{{GrowthPercent: 400.0}},
		}
		if !res.IsHighConfidence() {
			t.Error("expected high confidence for mirror + arch")
		}
	})
}
