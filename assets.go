package main

import "embed"

//go:embed dist/assets
var embedAssets embed.FS

//go:embed static
var embedStatic embed.FS

//go:embed dist/manifest.json
var embedManifest []byte
