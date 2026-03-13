package main

import (
	"encoding/json"
	"os"

	"pkgstatsd/internal/apidoc"
)

func main() {
	data, _ := json.MarshalIndent(apidoc.BuildSpec(true), "", "    ")
	_, _ = os.Stdout.Write(data)
}
