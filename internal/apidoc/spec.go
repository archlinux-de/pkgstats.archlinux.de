package apidoc

import (
	"pkgstatsd/internal/submit"
	"pkgstatsd/internal/web"
)

type OpenAPISpec struct {
	OpenAPI    string              `json:"openapi"`
	Info       SpecInfo            `json:"info"`
	Tags       []SpecTag           `json:"tags,omitempty"`
	Paths      map[string]PathItem `json:"paths"`
	Components SpecComponents      `json:"components"`
}

type SpecInfo struct {
	Title   string `json:"title"`
	Version string `json:"version"`
}

type SpecTag struct {
	Name        string `json:"name"`
	Description string `json:"description,omitempty"`
}

type PathItem struct {
	Get  *Operation `json:"get,omitempty"`
	Post *Operation `json:"post,omitempty"`
}

type Operation struct {
	Tags        []string            `json:"tags,omitempty"`
	Summary     string              `json:"summary,omitempty"`
	OperationID string              `json:"operationId,omitempty"`
	Parameters  []Parameter         `json:"parameters,omitempty"`
	RequestBody *RequestBody        `json:"requestBody,omitempty"`
	Responses   map[string]Response `json:"responses"`
}

type Parameter struct {
	Name        string  `json:"name"`
	In          string  `json:"in"`
	Description string  `json:"description,omitempty"`
	Required    bool    `json:"required,omitempty"`
	Schema      *Schema `json:"schema,omitempty"`
}

type RequestBody struct {
	Required bool                 `json:"required,omitempty"`
	Content  map[string]MediaType `json:"content"`
}

type MediaType struct {
	Schema *Schema `json:"schema,omitempty"`
}

type Response struct {
	Description string               `json:"description"`
	Content     map[string]MediaType `json:"content,omitempty"`
}

type Schema struct {
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
	Properties map[string]*Schema `json:"properties,omitempty"`
	Items      *Schema            `json:"items,omitempty"`
}

type SpecComponents struct {
	Schemas map[string]*Schema `json:"schemas"`
}

type entitySpec struct {
	basePath        string
	pathParam       string
	pathParamDesc   string
	tag             string
	itemSchemaName  string
	listSchemaName  string
	identifierField string
	collectionField string
	internal        bool
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
	paramStartMonth = Parameter{
		Name:        "startMonth",
		In:          "query",
		Description: "Start month in Ym format (e.g. 202501). Defaults to 12 months ago.",
		Schema:      &Schema{Type: "integer"},
	}
	paramEndMonth = Parameter{
		Name:        "endMonth",
		In:          "query",
		Description: "End month in Ym format (e.g. 202501). Defaults to last month.",
		Schema:      &Schema{Type: "integer"},
	}
	paramLimit = Parameter{
		Name:        "limit",
		In:          "query",
		Description: "Maximum number of results to return.",
		Schema:      &Schema{Type: "integer", Default: web.DefaultLimit, Minimum: new(1), Maximum: new(web.MaxLimit)},
	}
	paramOffset = Parameter{
		Name:        "offset",
		In:          "query",
		Description: "Number of results to skip.",
		Schema:      &Schema{Type: "integer", Default: 0, Minimum: new(0), Maximum: new(web.MaxOffset)},
	}
	paramQuery = Parameter{
		Name:        "query",
		In:          "query",
		Description: "Filter by name.",
		Schema:      &Schema{Type: "string", MaxLength: new(submit.MaxPackageLen)},
	}
)

func popularityItemSchema(identifierField string) *Schema {
	return &Schema{
		Type:     "object",
		Required: []string{identifierField, "samples", "count", "popularity", "startMonth", "endMonth"},
		Properties: map[string]*Schema{
			identifierField: {Type: "string"},
			"samples":       {Type: "integer"},
			"count":         {Type: "integer"},
			"popularity":    {Type: "number", Format: "float"},
			"startMonth":    {Type: "integer"},
			"endMonth":      {Type: "integer"},
		},
	}
}

func popularityListSchema(collectionField, itemSchemaRef string) *Schema {
	return &Schema{
		Type:     "object",
		Required: []string{collectionField, "total", "count", "limit", "offset"},
		Properties: map[string]*Schema{
			collectionField: {
				Type:  "array",
				Items: &Schema{Ref: itemSchemaRef},
			},
			"total":  {Type: "integer"},
			"count":  {Type: "integer"},
			"limit":  {Type: "integer"},
			"offset": {Type: "integer"},
			"query":  {Type: "string", Nullable: true},
		},
	}
}

func jsonResponse(schemaName string) map[string]Response {
	return map[string]Response{
		"200": {
			Description: "Success",
			Content: map[string]MediaType{
				"application/json": {Schema: &Schema{Ref: "#/components/schemas/" + schemaName}},
			},
		},
		"400": {Description: "Invalid request"},
		"500": {Description: "Internal server error"},
	}
}

func BuildSpec(includeInternal bool) *OpenAPISpec {
	spec := &OpenAPISpec{
		OpenAPI: "3.0.0",
		Info: SpecInfo{
			Title:   "pkgstats API documentation",
			Version: "3.0.0",
		},
		Paths:      make(map[string]PathItem),
		Components: SpecComponents{Schemas: make(map[string]*Schema)},
	}

	for _, e := range popularityEntities {
		if !includeInternal && e.internal {
			continue
		}
		spec.Tags = append(spec.Tags, SpecTag{Name: e.tag})

		itemSchemaRef := "#/components/schemas/" + e.itemSchemaName
		spec.Components.Schemas[e.itemSchemaName] = popularityItemSchema(e.identifierField)
		spec.Components.Schemas[e.listSchemaName] = popularityListSchema(e.collectionField, itemSchemaRef)

		pathParam := Parameter{
			Name:        e.pathParam,
			In:          "path",
			Description: e.pathParamDesc,
			Required:    true,
			Schema:      &Schema{Type: "string"},
		}

		spec.Paths[e.basePath] = PathItem{
			Get: &Operation{
				Tags:        []string{e.tag},
				Summary:     "List " + e.tag,
				OperationID: "list_" + e.tag,
				Parameters:  []Parameter{paramStartMonth, paramEndMonth, paramLimit, paramOffset, paramQuery},
				Responses:   jsonResponse(e.listSchemaName),
			},
		}
		spec.Paths[e.basePath+"/{"+e.pathParam+"}"] = PathItem{
			Get: &Operation{
				Tags:        []string{e.tag},
				Summary:     "Get " + e.tag + " by " + e.pathParam,
				OperationID: "get_" + e.tag + "_by_" + e.pathParam,
				Parameters:  []Parameter{pathParam, paramStartMonth, paramEndMonth},
				Responses:   jsonResponse(e.itemSchemaName),
			},
		}
		spec.Paths[e.basePath+"/{"+e.pathParam+"}/series"] = PathItem{
			Get: &Operation{
				Tags:        []string{e.tag},
				Summary:     "List " + e.tag + " series by " + e.pathParam,
				OperationID: "list_" + e.tag + "_series_by_" + e.pathParam,
				Parameters:  []Parameter{pathParam, paramStartMonth, paramEndMonth, paramLimit, paramOffset},
				Responses:   jsonResponse(e.listSchemaName),
			},
		}
	}

	if includeInternal {
		spec.Tags = append(spec.Tags, SpecTag{Name: "submit"})
		spec.Paths["/api/submit"] = PathItem{
			Post: &Operation{
				Tags:        []string{"submit"},
				Summary:     "Submit package statistics",
				OperationID: "submit",
				RequestBody: &RequestBody{
					Required: true,
					Content: map[string]MediaType{
						"application/json": {Schema: &Schema{Ref: "#/components/schemas/SubmitRequest"}},
					},
				},
				Responses: map[string]Response{
					"204": {Description: "Statistics accepted"},
					"400": {Description: "Invalid request"},
					"429": {Description: "Rate limit exceeded"},
					"500": {Description: "Internal server error"},
				},
			},
		}
		spec.Components.Schemas["SubmitRequest"] = &Schema{
			Type:     "object",
			Required: []string{"version", "system", "os", "pacman"},
			Properties: map[string]*Schema{
				"version": {Type: "string", Enum: []string{"3"}},
				"system": {
					Type:     "object",
					Required: []string{"architecture"},
					Properties: map[string]*Schema{
						"architecture": {Type: "string"},
					},
				},
				"os": {
					Type:     "object",
					Required: []string{"architecture"},
					Properties: map[string]*Schema{
						"architecture": {Type: "string"},
						"id":           {Type: "string", Pattern: `^[0-9a-z._-]{1,50}$`},
					},
				},
				"pacman": {
					Type:     "object",
					Required: []string{"packages"},
					Properties: map[string]*Schema{
						"mirror": {Type: "string"},
						"packages": {
							Type:     "array",
							Items:    &Schema{Type: "string"},
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
