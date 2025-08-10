---
title: Deploying a static website to Azure Storage from AppVeyor
date: 2018-06-30
tags: azure
layout: post
description: "How to deploy a static website to an Azure Storage container from AppVeyor CI."
---

This week the Azure storage team [finally announced](https://azure.microsoft.com/en-gb/blog/azure-storage-static-web-hosting-public-preview/ "Static website hosting for Azure Storage now in public preview") that Azure Storage now support hosting static websites. This has been a [long-standing](https://feedback.azure.com/forums/217298-storage/suggestions/6417741-static-website-hosting-in-azure-blob-storage "Static website hosting in Azure blob storage") request from users of Azure (for nearly 4 years), so it's great to see something now available for use, even if at the time of writing it's currently only in public preview.

I've been hosting this blog in [AWS for almost a year now](https://blog.martincostello.com/migrating-from-iis-to-s3/ "Migrating to Amazon S3"), so I thought I'd give the public preview a try and automate deployment with [AppVeyor](https://www.appveyor.com/ "AppVeyor CI") as well at the same time.

READMORE

Part of the primary reason this blog is hosted in AWS is because it wasn't possible to host the static site in Azure, which is my preferred cloud hosting provider for most of my infrastructure. But as that wasn't possible I went with S3 for content and AWS Lambda@Edge to handle IIS-style folders and custom HTTP response headers. You can read more about that in this blog post: [https://blog.martincostello.com/migrating-from-iis-to-s3/](https://blog.martincostello.com/migrating-from-iis-to-s3/ "Migrating to Amazon S3")

I've used AppVeyor to deploy content to Azure storage before as it's used to host my [CDN](https://github.com/martincostello/cdn "cdn on GitHub.com") where I store images and other content that this blog and some of my other sites use to reduce duplication, so I thought I'd use that as a starting point for the implementation.

All-in it actually only took about 45 minutes to setup and configure, so it's surprisingly easy to start using. I just followed the Getting Started instructions in the announcement blog post and then [opened a Pull Request](https://github.com/martincostello/blog/pull/37 "Deploy to Azure storage static website") on my blog repo in GitHub to test it out.

The approximate steps to enable Azure static website hosting for this blog were:

  1. Create a new Azure Storage General Purpose v2 storage account.
  1. Enable _Static website_ to create the special `$web` container.
  1. Set the file names to use as the index and error pages.
  1. Enable the GitHub repo for [AppVeyor CI](https://ci.appveyor.com/project/martincostello/blog "blog in AppVeyor CI").
  1. Add an [`appveyor.yml`](https://github.com/martincostello/blog/blob/main/appveyor.yml "appveyor.yml on GitHub.com") file to build the static site and upload the files to it.

[Here's the first AppVeyor build from my `deploy` branch](https://ci.appveyor.com/project/martincostello/blog/build/8 "First deployment") to upload the content from earlier today.

There's plenty of extra stuff I'd need to do to migrate back from AWS to Azure once this comes out of public preview if I choose to move, but it's nice to see that the mechanics of uploading content are easy to get moving with. These extras include:

- Deleting content from the storage container no longer present in source.
- Configuring a custom DNS hostname.
- Fronting it with Azure CDN to support custom TLS certificates.
- Configuring appropriate caching headers.
- Configuring HTTP response headers for things like having a Content Security Policy to get an A+ rating in tools such as [securityheaders.io](https://securityheaders.com/ "securityheaders.io").

With that all set up, here's this blog post served from Azure storage: [https://martincostelloblog.z33.web.core.windows.net/azure-static-websites-with-appveyor/](https://martincostelloblog.z33.web.core.windows.net/azure-static-websites-with-appveyor/ "Blog from Azure Storage")

Relatively painless and quick to turn around, this looks like a feature that will be useful for those with a strong preference for Windows Azure to keep their different types of infrastructure co-located (and leverage their Azure MSDN credits too).

Happy coding!
