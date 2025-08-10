if (self === top) {
    document.documentElement.className = document.documentElement.className.replace(/\bjs-flash\b/, '');
}
else {
    top.location = self.location;
}

window.addEventListener('load', async () => {
    setTimeout(() => {
            const images = document.querySelectorAll('img.lazy');
            for (const image of images) {
                let url = image.getAttribute('data-original');
                url = encodeURI(url);
                image.setAttribute('src', url);
                image.removeAttribute('data-original');
            }
        }, 500);

    if ("serviceWorker" in navigator) {
        try {
            await navigator.serviceWorker.register("/service-worker.js")
        } catch (e) {
            console.error("Failed to register Service Worker: ", e);
        }
    }
});

// Twitter
!function (d, s, id) { var js, fjs = d.getElementsByTagName(s)[0], p = /^http:/.test(d.location) ? 'http' : 'https'; if (!d.getElementById(id)) { js = d.createElement(s); js.id = id; js.defer = ""; js.src = p + '://platform.twitter.com/widgets.js'; fjs.parentNode.insertBefore(js, fjs); } }(document, 'script', 'twitter-wjs');

// Google Analytics - will be injected by Hugo template
if (window.hugoSiteParams && window.hugoSiteParams.render_analytics) {
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());
    gtag('config', window.hugoSiteParams.analytics_id);
}