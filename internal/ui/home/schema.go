package home

func webSiteSchema(baseURL string) any {
	return map[string]any{
		"@context": "https://schema.org",
		"@type":    "WebSite",
		"name":     "Arch Linux Package Statistics",
		"url":      baseURL,
		"potentialAction": map[string]any{
			"@type":       "SearchAction",
			"target":      baseURL + "/packages?query={search_term}",
			"query-input": "required name=search_term",
		},
	}
}

func datasetSchema(baseURL string) any {
	return map[string]any{
		"@context":    "https://schema.org",
		"@type":       "Dataset",
		"name":        "Arch Linux Package Statistics",
		"description": "Aggregated usage statistics of Arch Linux packages, system architectures, operating systems, and mirrors, collected from voluntary pkgstats submissions.",
		"url":         baseURL,
		"creator": map[string]any{
			"@type": "Organization",
			"name":  "pkgstats",
			"url":   baseURL,
		},
		"license": "https://creativecommons.org/licenses/by-sa/4.0/",
		"distribution": map[string]any{
			"@type":          "DataDownload",
			"encodingFormat": "application/json",
			"contentUrl":     baseURL + "/api/packages",
		},
	}
}
