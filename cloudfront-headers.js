// Copyright (c) Martin Costello, 2017. All rights reserved.
// Licensed under the MIT. See the LICENSE file in the project root for full license information.

"use strict";

exports.handler = (event, context, callback) => {
    const response = event.Records[0].cf.response;
    const headers = response.headers;

    const values = [
        { key: "Content-Security-Policy", value: "default-src 'self'; child-src 'self' disqus.com platform.twitter.com syndication.twitter.com; frame-src disqus.com platform.twitter.com syndication.twitter.com twitter.com; font-src 'self' fonts.gstatic.com maxcdn.bootstrapcdn.com; img-src 'self' a.disquscdn.com martincostello.azureedge.net csi.gstatic.com o.twimg.com pbs.twimg.com platform.twitter.com referrer.disqus.com ssl.google-analytics.com stats.g.doubleclick.net syndication.twitter.com ton.twimg.com data:; script-src 'self' ajax.googleapis.com apis.google.com cdn.syndication.twimg.com cdnjs.cloudflare.com connect.facebook.net c.disquscdn.com disqus.com martincostelloblog.disqus.com maxcdn.bootstrapcdn.com platform.twitter.com ssl.google-analytics.com www.google-analytics.com 'unsafe-inline'; style-src 'self' a.disquscdn.com c.disquscdn.com fonts.googleapis.com maxcdn.bootstrapcdn.com platform.twitter.com ton.twimg.com 'unsafe-inline'; report-uri https://martincostello.report-uri.io/r/default/csp/reportOnly;" },
        { key: "Expect-CT", value: "max-age=1800; report-uri https://martincostello.report-uri.io/r/default/ct/reportOnly" },
        { key: "Referrer-Policy", value: "no-referrer-when-downgrade" },
        { key: "Strict-Transport-Security", value: "max-age=31536000" },
        { key: "X-Content-Type-Options", value: "nosniff" },
        { key: "X-Download-Options", value: "noopen" },
        { key: "X-Frame-Options", value: "DENY" },
        { key: "X-XSS-Protection", value: "1; mode=block" }
    ];

    values.forEach((header) => {
        headers[header.key] = [header];
    });

    callback(null, response);
};
