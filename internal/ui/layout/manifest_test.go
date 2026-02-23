package layout

import (
	"testing"
)

// realManifest mirrors the structure produced by Vite: one entry point with
// CSS, several non-entry chunks (dynamic imports, vendor splits, etc.).
var realManifest = []byte(`{
  "__commonjsHelpers-CLPN-Npl.js": {
    "file": "assets/_commonjsHelpers-CLPN-Npl.js",
    "name": "_commonjsHelpers"
  },
  "_index-BmL2jti6.js": {
    "file": "assets/index-BmL2jti6.js",
    "name": "index"
  },
  "_index-bUj5cbfw.js": {
    "file": "assets/index-bUj5cbfw.js",
    "name": "index",
    "isDynamicEntry": true,
    "imports": ["_index-BmL2jti6.js"]
  },
  "node_modules/.pnpm/chart.js@4.5.1/node_modules/chart.js/dist/chart.js": {
    "file": "assets/chart-BjMzFfek.js",
    "name": "chart",
    "isDynamicEntry": true
  },
  "src/main.ts": {
    "file": "assets/main-CxdDuL5j.js",
    "name": "main",
    "src": "src/main.ts",
    "isEntry": true,
    "dynamicImports": ["node_modules/.pnpm/chart.js@4.5.1/node_modules/chart.js/dist/chart.js"],
    "css": ["assets/main-QTw0RKdw.css"]
  }
}`)

func TestNewManifest_ParsesEntryPoint(t *testing.T) {
	manifest, err := NewManifest(realManifest)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if len(manifest.JS) != 1 || manifest.JS[0] != "/assets/main-CxdDuL5j.js" {
		t.Errorf("JS = %v, want [/assets/main-CxdDuL5j.js]", manifest.JS)
	}

	if len(manifest.CSS) != 1 || manifest.CSS[0] != "/assets/main-QTw0RKdw.css" {
		t.Errorf("CSS = %v, want [/assets/main-QTw0RKdw.css]", manifest.CSS)
	}
}

func TestNewManifest_SkipsNonEntryChunks(t *testing.T) {
	manifest, err := NewManifest(realManifest)
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if len(manifest.JS) != 1 {
		t.Errorf("expected 1 JS entry, got %d: %v", len(manifest.JS), manifest.JS)
	}

	if len(manifest.CSS) != 1 {
		t.Errorf("expected 1 CSS entry, got %d: %v", len(manifest.CSS), manifest.CSS)
	}
}

func TestNewManifest_EmptyManifest(t *testing.T) {
	manifest, err := NewManifest([]byte(`{}`))
	if err != nil {
		t.Fatalf("unexpected error: %v", err)
	}

	if len(manifest.JS) != 0 || len(manifest.CSS) != 0 {
		t.Errorf("expected empty manifest, got JS=%v CSS=%v", manifest.JS, manifest.CSS)
	}
}

func TestNewManifest_InvalidJSON(t *testing.T) {
	_, err := NewManifest([]byte(`not json`))
	if err == nil {
		t.Error("expected error for invalid JSON, got nil")
	}
}
