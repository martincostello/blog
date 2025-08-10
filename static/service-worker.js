'use strict';

self.addEventListener('install', function (event) {
  event.waitUntil(
    caches
      .open('blog.martincostello.com')
      .then(function (cache) {
        return cache.addAll(['/']);
      })
      .then(function () {
        return self.skipWaiting();
      })
  );
});

self.addEventListener('activate', function (event) {});
