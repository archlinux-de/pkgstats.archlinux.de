package packagepage

import _ "embed"

//go:generate cp ../../../node_modules/bootstrap-icons/icons/plus-lg.svg icons/plus-lg.svg
//go:generate cp ../../../node_modules/bootstrap-icons/icons/trash.svg icons/trash.svg

//go:embed icons/plus-lg.svg
var iconPlus string

//go:embed icons/trash.svg
var iconTrash string
