function redirectPackagesHash(): void {
    const { pathname, hash, origin } = window.location;
    if (pathname.replace(/\/$/, "") !== "/packages") {
        return;
    }

    const params = new URLSearchParams(hash.replace(/^#/, ""));
    const query = params.get("query");
    if (query) {
        const newUrl = `${origin}/packages?query=${encodeURIComponent(query)}`;

        const canonical = document.querySelector('link[rel="canonical"]');
        if (canonical) {
            canonical.setAttribute("href", newUrl);
        }

        window.location.replace(newUrl);
    }
}

redirectPackagesHash();
