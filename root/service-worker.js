// Kill switch for the legacy Workbox service worker.
// Can be removed once /service-worker.js no longer appears in the nginx access log.
self.addEventListener("install", () => self.skipWaiting());
self.addEventListener("activate", async () => {
    await caches.keys().then((keys) => Promise.all(keys.map((k) => caches.delete(k))));
    await self.registration.unregister();
});
