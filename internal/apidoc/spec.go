package apidoc

import (
	"pkgstatsd/internal/submit"
	"pkgstatsd/internal/web"
)

type openAPISpec struct {
	OpenAPI    string              `json:"openapi"`
	Info       specInfo            `json:"info"`
	Tags       []specTag           `json:"tags,omitempty"`
	Paths      map[string]pathItem `json:"paths"`
	Components specComponents      `json:"components"`
}

type specInfo struct {
	Title   string `json:"title"`
	Version string `json:"version"`
}

type specTag struct {
	Name        string `json:"name"`
	Description string `json:"description,omitempty"`
}

type pathItem struct {
	Get  *operation `json:"get,omitempty"`
	Post *operation `json:"post,omitempty"`
}

type operation struct {
	Tags        []string            `json:"tags,omitempty"`
	Summary     string              `json:"summary,omitempty"`
	OperationID string              `json:"operationId,omitempty"`
	Parameters  []parameter         `json:"parameters,omitempty"`
	RequestBody *requestBody        `json:"requestBody,omitempty"`
	Responses   map[string]response `json:"responses"`
}

type parameter struct {
	Name        string  `json:"name"`
	In          string  `json:"in"`
	Description string  `json:"description,omitempty"`
	Required    bool    `json:"required,omitempty"`
	Schema      *schema `json:"schema,omitempty"`
}

type requestBody struct {
	Required bool                 `json:"required,omitempty"`
	Content  map[string]mediaType `json:"content"`
}

type mediaType struct {
	Schema *schema `json:"schema,omitempty"`
}

type response struct {
	Description string               `json:"description"`
	Content     map[string]mediaType `json:"content,omitempty"`
}

type schema struct {
	Ref        string             `json:"$ref,omitempty"`
	Type       string             `json:"type,omitempty"`
	Format     string             `json:"format,omitempty"`
	Pattern    string             `json:"pattern,omitempty"`
	Enum       []string           `json:"enum,omitempty"`
	Default    any                `json:"default,omitempty"`
	Minimum    *int               `json:"minimum,omitempty"`
	Maximum    *int               `json:"maximum,omitempty"`
	MaxLength  *int               `json:"maxLength,omitempty"`
	MinItems   *int               `json:"minItems,omitempty"`
	MaxItems   *int               `json:"maxItems,omitempty"`
	Nullable   bool               `json:"nullable,omitempty"`
	Required   []string           `json:"required,omitempty"`
	Properties map[string]*schema `json:"properties,omitempty"`
	Items      *schema            `json:"items,omitempty"`
}

type specComponents struct {
	Schemas map[string]*schema `json:"schemas"`
}

type entitySpec struct {
	basePath        string // e.g. "/api/packages"
	pathParam       string // e.g. "name"
	pathParamDesc   string // e.g. "Package name"
	tag             string // e.g. "packages"
	itemSchemaName  string // e.g. "PackagePopularity"
	listSchemaName  string // e.g. "PackagePopularityList"
	identifierField string // JSON field for the identifier in the item (e.g. "name")
	collectionField string // JSON field for items in the list (e.g. "packagePopularities")
	internal        bool   // omit from the spec in production
}

var popularityEntities = []entitySpec{
	{
		basePath:        "/api/packages",
		pathParam:       "name",
		pathParamDesc:   "Package name",
		tag:             "packages",
		itemSchemaName:  "PackagePopularity",
		listSchemaName:  "PackagePopularityList",
		identifierField: "name",
		collectionField: "packagePopularities",
	},
	{
		basePath:        "/api/countries",
		pathParam:       "code",
		pathParamDesc:   "ISO 3166-1 alpha-2 country code",
		tag:             "countries",
		itemSchemaName:  "CountryPopularity",
		listSchemaName:  "CountryPopularityList",
		identifierField: "code",
		collectionField: "countryPopularities",
		internal:        true,
	},
	{
		basePath:        "/api/mirrors",
		pathParam:       "url",
		pathParamDesc:   "Mirror URL",
		tag:             "mirrors",
		itemSchemaName:  "MirrorPopularity",
		listSchemaName:  "MirrorPopularityList",
		identifierField: "url",
		collectionField: "mirrorPopularities",
		internal:        true,
	},
	{
		basePath:        "/api/system-architectures",
		pathParam:       "name",
		pathParamDesc:   "System architecture name",
		tag:             "system-architectures",
		itemSchemaName:  "SystemArchitecturePopularity",
		listSchemaName:  "SystemArchitecturePopularityList",
		identifierField: "name",
		collectionField: "systemArchitecturePopularities",
		internal:        true,
	},
	{
		basePath:        "/api/operating-systems",
		pathParam:       "id",
		pathParamDesc:   "Operating system ID",
		tag:             "operating-systems",
		itemSchemaName:  "OperatingSystemIdPopularity",
		listSchemaName:  "OperatingSystemIdPopularityList",
		identifierField: "id",
		collectionField: "operatingSystemIdPopularities",
		internal:        true,
	},
	{
		basePath:        "/api/operating-system-architectures",
		pathParam:       "name",
		pathParamDesc:   "OS architecture name",
		tag:             "operating-system-architectures",
		itemSchemaName:  "OperatingSystemArchitecturePopularity",
		listSchemaName:  "OperatingSystemArchitecturePopularityList",
		identifierField: "name",
		collectionField: "operatingSystemArchitecturePopularities",
		internal:        true,
	},
}

var (
	paramStartMonth = parameter{
		Name:        "startMonth",
		In:          "query",
		Description: "Start month in Ym format (e.g. 202501). Defaults to 12 months ago.",
		Schema:      &schema{Type: "integer"},
	}
	paramEndMonth = parameter{
		Name:        "endMonth",
		In:          "query",
		Description: "End month in Ym format (e.g. 202501). Defaults to last month.",
		Schema:      &schema{Type: "integer"},
	}
	paramLimit = parameter{
		Name:        "limit",
		In:          "query",
		Description: "Maximum number of results to return.",
		Schema:      &schema{Type: "integer", Default: web.DefaultLimit, Minimum: new(1), Maximum: new(web.MaxLimit)},
	}
	paramOffset = parameter{
		Name:        "offset",
		In:          "query",
		Description: "Number of results to skip.",
		Schema:      &schema{Type: "integer", Default: 0, Minimum: new(0), Maximum: new(web.MaxOffset)},
	}
	paramQuery = parameter{
		Name:        "query",
		In:          "query",
		Description: "Filter by name.",
		Schema:      &schema{Type: "string", MaxLength: new(submit.MaxPackageLen)},
	}
)

func popularityItemSchema(identifierField string) *schema {
	return &schema{
		Type:     "object",
		Required: []string{identifierField, "samples", "count", "popularity", "startMonth", "endMonth"},
		Properties: map[string]*schema{
			identifierField: {Type: "string"},
			"samples":       {Type: "integer"},
			"count":         {Type: "integer"},
			"popularity":    {Type: "number", Format: "float"},
			"startMonth":    {Type: "integer"},
			"endMonth":      {Type: "integer"},
		},
	}
}

func popularityListSchema(collectionField, itemSchemaRef string) *schema {
	return &schema{
		Type:     "object",
		Required: []string{collectionField, "total", "count", "limit", "offset"},
		Properties: map[string]*schema{
			collectionField: {
				Type:  "array",
				Items: &schema{Ref: itemSchemaRef},
			},
			"total":  {Type: "integer"},
			"count":  {Type: "integer"},
			"limit":  {Type: "integer"},
			"offset": {Type: "integer"},
			"query":  {Type: "string", Nullable: true},
		},
	}
}

func jsonResponse(schemaName string) map[string]response {
	return map[string]response{
		"200": {
			Description: "Success",
			Content: map[string]mediaType{
				"application/json": {Schema: &schema{Ref: "#/components/schemas/" + schemaName}},
			},
		},
		"400": {Description: "Invalid request"},
		"500": {Description: "Internal server error"},
	}
}

func buildSpec(includeInternal bool) *openAPISpec {
	spec := &openAPISpec{
		OpenAPI: "3.0.0",
		Info: specInfo{
			Title:   "pkgstats API documentation",
			Version: "3.0.0",
		},
		Paths:      make(map[string]pathItem),
		Components: specComponents{Schemas: make(map[string]*schema)},
	}

	for _, e := range popularityEntities {
		if !includeInternal && e.internal {
			continue
		}
		spec.Tags = append(spec.Tags, specTag{Name: e.tag})

		itemSchemaRef := "#/components/schemas/" + e.itemSchemaName
		spec.Components.Schemas[e.itemSchemaName] = popularityItemSchema(e.identifierField)
		spec.Components.Schemas[e.listSchemaName] = popularityListSchema(e.collectionField, itemSchemaRef)

		pathParam := parameter{
			Name:        e.pathParam,
			In:          "path",
			Description: e.pathParamDesc,
			Required:    true,
			Schema:      &schema{Type: "string"},
		}

		spec.Paths[e.basePath] = pathItem{
			Get: &operation{
				Tags:        []string{e.tag},
				Summary:     "List " + e.tag,
				OperationID: "list_" + e.tag,
				Parameters:  []parameter{paramStartMonth, paramEndMonth, paramLimit, paramOffset, paramQuery},
				Responses:   jsonResponse(e.listSchemaName),
			},
		}
		spec.Paths[e.basePath+"/{"+e.pathParam+"}"] = pathItem{
			Get: &operation{
				Tags:        []string{e.tag},
				Summary:     "Get " + e.tag + " by " + e.pathParam,
				OperationID: "get_" + e.tag + "_by_" + e.pathParam,
				Parameters:  []parameter{pathParam, paramStartMonth, paramEndMonth},
				Responses:   jsonResponse(e.itemSchemaName),
			},
		}
		spec.Paths[e.basePath+"/{"+e.pathParam+"}/series"] = pathItem{
			Get: &operation{
				Tags:        []string{e.tag},
				Summary:     "List " + e.tag + " series by " + e.pathParam,
				OperationID: "list_" + e.tag + "_series_by_" + e.pathParam,
				Parameters:  []parameter{pathParam, paramStartMonth, paramEndMonth, paramLimit, paramOffset},
				Responses:   jsonResponse(e.listSchemaName),
			},
		}
	}

	if includeInternal {
		spec.Tags = append(spec.Tags, specTag{Name: "submit"})
		spec.Paths["/api/submit"] = pathItem{
			Post: &operation{
				Tags:        []string{"submit"},
				Summary:     "Submit package statistics",
				OperationID: "submit",
				RequestBody: &requestBody{
					Required: true,
					Content: map[string]mediaType{
						"application/json": {Schema: &schema{Ref: "#/components/schemas/SubmitRequest"}},
					},
				},
				Responses: map[string]response{
					"204": {Description: "Statistics accepted"},
					"400": {Description: "Invalid request"},
					"429": {Description: "Rate limit exceeded"},
					"500": {Description: "Internal server error"},
				},
			},
		}
		spec.Components.Schemas["SubmitRequest"] = &schema{
			Type:     "object",
			Required: []string{"version", "system", "os", "pacman"},
			Properties: map[string]*schema{
				"version": {Type: "string", Enum: []string{"3"}},
				"system": {
					Type:     "object",
					Required: []string{"architecture"},
					Properties: map[string]*schema{
						"architecture": {Type: "string"},
					},
				},
				"os": {
					Type:     "object",
					Required: []string{"architecture"},
					Properties: map[string]*schema{
						"architecture": {Type: "string"},
						"id":           {Type: "string", Pattern: `^[0-9a-z._-]{1,50}$`},
					},
				},
				"pacman": {
					Type:     "object",
					Required: []string{"packages"},
					Properties: map[string]*schema{
						"mirror": {Type: "string"},
						"packages": {
							Type:     "array",
							Items:    &schema{Type: "string"},
							MinItems: new(1),
							MaxItems: new(submit.MaxPackages),
						},
					},
				},
			},
		}
	}

	return spec
}
