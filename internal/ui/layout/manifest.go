package layout

import (
	"encoding/json"
	"fmt"
)

type manifestItem struct {
	File string   `json:"file"`
	CSS  []string `json:"css"`
}

type Manifest struct {
	JS  []string
	CSS []string
}

func NewManifest(manifestFile []byte) (*Manifest, error) {
	var result map[string]manifestItem
	if err := json.Unmarshal(manifestFile, &result); err != nil {
		return nil, fmt.Errorf("failed to unmarshal manifest: %w", err)
	}

	jsFiles := make([]string, 0, len(result))
	cssFiles := make([]string, 0, len(result))

	for _, item := range result {
		if item.File != "" {
			jsFiles = append(jsFiles, "/"+item.File)
		}
		if len(item.CSS) > 0 {
			for _, cssFile := range item.CSS {
				cssFiles = append(cssFiles, "/"+cssFile)
			}
		}
	}

	return &Manifest{
		JS:  jsFiles,
		CSS: cssFiles,
	}, nil
}
