//go:build !production

package main

import "embed"

var embedAssets embed.FS

//go:embed static
var embedStatic embed.FS

var embedManifest = []byte(`{}`)
