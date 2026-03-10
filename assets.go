//go:build production || development

package main

import "embed"

//go:embed all:dist/assets
var embedAssets embed.FS

//go:embed static
var embedStatic embed.FS

//go:embed root
var embedRoot embed.FS

//go:embed dist/manifest.json
var embedManifest []byte
