package layout

import "time"

const (
	MonthMultiplier = 100
	SeriesLimit     = 10000
	MaxEndMonth     = 999912
)

func CurrentMonth() int {
	now := time.Now()
	return now.Year()*MonthMultiplier + int(now.Month())
}
