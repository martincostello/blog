if (self === top) {
  document.documentElement.className = document.documentElement.className.replace(
    /\bjs-flash\b/,
    ''
  );
} else {
  top.location = self.location;
}

const siteParameters = {
  analyticsId: document.querySelector('meta[name="martincostello:blog:analytics-id"]').content,
  renderAnalytics:
    document.querySelector('meta[name="martincostello:blog:render-analytics"]').content === 'true',
  version: document.querySelector('meta[name="martincostello:blog:version"]').content,
};

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

  if ('serviceWorker' in navigator) {
    try {
      const version = siteParameters?.version || '';
      await navigator.serviceWorker.register(
        '/service-worker.js?' + new URLSearchParams({ v: version })
      );
    } catch (e) {
      console.error('Failed to register Service Worker: ', e);
    }
  }
});

// Twitter
!(function (d, s, id) {
  var js,
    fjs = d.getElementsByTagName(s)[0],
    p = /^http:/.test(d.location) ? 'http' : 'https';
  if (!d.getElementById(id)) {
    js = d.createElement(s);
    js.id = id;
    js.defer = '';
    js.src = p + '://platform.twitter.com/widgets.js';
    fjs.parentNode.insertBefore(js, fjs);
  }
})(document, 'script', 'twitter-wjs');

// Google Analytics
if (siteParameters.renderAnalytics) {
  window.dataLayer = window.dataLayer || [];
  function gtag() {
    dataLayer.push(arguments);
  }
  gtag('js', new Date());
  gtag('config', siteParameters.analyticsId);
}
