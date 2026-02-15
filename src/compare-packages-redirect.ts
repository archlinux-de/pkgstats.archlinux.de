function syncAndRedirect(): void {
  const { pathname, hash, origin } = window.location;
  if (pathname.replace(/\/$/, '') !== '/compare/packages') return;

  const match = hash.match(/^#packages=([\w\-,]+)$/);
  if (match) {
    const newPath = `/compare/packages/${match[1]}`;
    const fullUrl = `${origin}${newPath}`;

    // Update Canonical Tag for SEO before redirecting
    const canonical = document.querySelector('link[rel="canonical"]');
    if (canonical) {
      canonical.setAttribute('href', fullUrl);
    }

    window.location.replace(fullUrl);
  }
}

syncAndRedirect();
