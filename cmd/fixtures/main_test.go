package main

import (
	"testing"
)

func TestRun(t *testing.T) {
	if err := run(":memory:"); err != nil {
		t.Fatal(err)
	}
}
