// Kill switch for the legacy Workbox service worker.
// This file should be served for at least 6 months after deployment (until ~2026-09)
// to ensure infrequent visitors also get the old SW unregistered, then it can be removed.
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", async () => {
    await caches.keys().then((keys) => Promise.all(keys.map((k) => caches.delete(k))));
    await self.registration.unregister();
});
