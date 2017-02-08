(function () {
    $("img.lazy").lazyload();

    if ("serviceWorker" in navigator) {
        navigator.serviceWorker
            .register("/service-worker.js")
            .then(function () {
                console.log("Service Worker registered.");
            })
            .catch(function (e) {
                console.error("Failed to register Service Worker: ", e);
            });
    }
})();
