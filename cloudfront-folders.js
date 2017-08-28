// Copyright (c) Martin Costello, 2017. All rights reserved.
// Licensed under the MIT. See the LICENSE file in the project root for full license information.

"use strict";

exports.handler = (event, context, callback) => {
    const request = event.Records[0].cf.request;
    const indexOfDot = request.uri.lastIndexOf('.');

    if (indexOfDot === -1) {

        let path = request.uri;

        if (path[path.length - 1] !== '/') {
            path = path += "/";
        }

        path = path += "index.html";
        request.uri = path;
    }

    callback(null, request);
};
