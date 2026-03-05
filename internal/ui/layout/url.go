package layout

import "net/http"

func GetBaseURL(r *http.Request) string {
	scheme := "http"
	if r.TLS != nil {
		scheme = "https"
	}

	if proto := r.Header.Get("X-Forwarded-Proto"); proto == "https" {
		scheme = proto
	}

	return scheme + "://" + r.Host
}
