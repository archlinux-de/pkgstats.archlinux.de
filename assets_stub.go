//go:build !production && !development

package main

import "embed"

var embedAssets embed.FS

//go:embed static
var embedStatic embed.FS

//go:embed root
var embedRoot embed.FS

var embedManifest = []byte(`{}`)
