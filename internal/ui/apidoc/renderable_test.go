package apidoc

import (
	"strings"
	"testing"

	"pkgstatsd/internal/apidoc"
)

var allowedPropertyTypes = map[string]bool{
	"string":  true,
	"integer": true,
	"number":  true,
	"array":   true,
}

var allowedTopLevelSchemaTypes = map[string]bool{
	"object": true,
}

var allowedParameterLocations = map[string]bool{
	"query": true,
	"path":  true,
}

func TestSpecIsRenderable(t *testing.T) {
	for _, includeInternal := range []bool{false, true} {
		spec := apidoc.BuildSpec(includeInternal)

		if len(spec.Paths) == 0 {
			t.Fatal("spec has no paths")
		}

		for path, item := range spec.Paths {
			if item.Get == nil {
				t.Errorf("path %q: missing GET operation", path)
				continue
			}
			assertOperationRenderable(t, path, item.Get, spec.Components)
		}

		for name, s := range spec.Components.Schemas {
			assertTopLevelSchemaRenderable(t, "schema "+name, s, spec.Components)
		}
	}
}

func assertOperationRenderable(t *testing.T, path string, op *apidoc.Operation, components apidoc.SpecComponents) {
	t.Helper()

	for _, p := range op.Parameters {
		if !allowedParameterLocations[p.In] {
			t.Errorf("path %q param %q: unsupported location %q", path, p.Name, p.In)
		}
		if p.Schema != nil {
			assertPropertySchemaRenderable(t, path+" param "+p.Name, p.Schema, components)
		}
	}

	for code, resp := range op.Responses {
		for _, media := range resp.Content {
			if media.Schema == nil {
				continue
			}
			if media.Schema.Ref == "" {
				t.Errorf("path %q response %s: expected $ref to component schema, got inline schema", path, code)
				continue
			}
			name := strings.TrimPrefix(media.Schema.Ref, "#/components/schemas/")
			if _, ok := components.Schemas[name]; !ok {
				t.Errorf("path %q response %s: unresolved $ref %q", path, code, media.Schema.Ref)
			}
		}
	}
}

func assertTopLevelSchemaRenderable(t *testing.T, context string, s *apidoc.Schema, components apidoc.SpecComponents) {
	t.Helper()

	if !allowedTopLevelSchemaTypes[s.Type] {
		t.Errorf("%s: unsupported top-level schema type %q", context, s.Type)
	}

	for propName, prop := range s.Properties {
		assertPropertySchemaRenderable(t, context+"."+propName, prop, components)
	}
}

func assertPropertySchemaRenderable(t *testing.T, context string, s *apidoc.Schema, components apidoc.SpecComponents) {
	t.Helper()

	if s.Ref != "" {
		name := strings.TrimPrefix(s.Ref, "#/components/schemas/")
		if _, ok := components.Schemas[name]; !ok {
			t.Errorf("%s: unresolved $ref %q", context, s.Ref)
		}
		return
	}

	if !allowedPropertyTypes[s.Type] {
		t.Errorf("%s: unsupported property type %q", context, s.Type)
	}

	if s.Type == "array" && s.Items != nil {
		if s.Items.Ref != "" {
			name := strings.TrimPrefix(s.Items.Ref, "#/components/schemas/")
			if _, ok := components.Schemas[name]; !ok {
				t.Errorf("%s: unresolved array item $ref %q", context, s.Items.Ref)
			}
		} else if !allowedPropertyTypes[s.Items.Type] {
			t.Errorf("%s: unsupported array item type %q", context, s.Items.Type)
		}
	}

	if s.Properties != nil {
		t.Errorf("%s: nested object properties not supported by renderer", context)
	}
}
