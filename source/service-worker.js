"use strict";

console.log("Started Service Worker.", self);

self.addEventListener("install", function (event) {
    event.waitUntil(
        caches.open("blog.martincostello.com").then(function (cache) {
            return cache.addAll([
                "/"
            ]);
        }).then(function () {
            return self.skipWaiting();
        })
    );
    console.log("Installed Service Worker.");
});

self.addEventListener("activate", function (event) {
    console.log("Activated Service Worker.");
});
