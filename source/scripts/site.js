(function () {

    if (self === top) {
        document.documentElement.className = document.documentElement.className.replace(/\bjs-flash\b/, '');
    }
    else {
        top.location = self.location;
    }

    $(window).on("load", function () {
        (function () {
            setTimeout(function () {
                $("img.lazy").lazyload();
            }, 500);
        })();
    });

    $("img.lazy").lazyload();

    if ("serviceWorker" in navigator) {
        navigator.serviceWorker
            .register("/service-worker.js")
            .then(function () {
            })
            .catch(function (e) {
                console.error("Failed to register Service Worker: ", e);
            });
    }
})();
