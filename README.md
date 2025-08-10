# Martin Costello's Blog

[![Build status](https://github.com/martincostello/blog/actions/workflows/build.yml/badge.svg?branch=main&event=push)](https://github.com/martincostello/blog/actions/workflows/build.yml?query=branch%3Amain+event%3Apush)

## Overview

Source code for building and deploying [blog.martincostello.com](https://blog.martincostello.com/).

## Feedback

Any feedback or issues can be added to the issues for this project in [GitHub](https://github.com/martincostello/blog/issues).

## Repository

The repository is hosted in [GitHub](https://github.com/martincostello/blog): <https://github.com/martincostello/blog.git>

## License

This project is licensed under the [MIT](https://github.com/martincostello/blog/blob/main/LICENSE) license.

## Building and Testing

To build the website run the following command (requires [Hugo](https://gohugo.io/)):

```sh
hugo --minify
```

Alternatively, you can use the PowerShell build script:

```sh
./build.ps1
```

To serve the website locally for development:

```sh
hugo server --buildDrafts --buildFuture
```

or:

```sh
./build.ps1 -Serve
```
