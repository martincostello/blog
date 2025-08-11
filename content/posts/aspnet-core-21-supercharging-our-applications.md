---
title: ASP.NET Core 2.1 – Supercharging Our Applications 🚀
date: 2018-06-14
tags: dotnet,aspnetcore,performance
layout: post
description: "Upgrading to ASP.NET Core 2.1 can deliver serious performance improvements to your web applications as well as make you much more productive as a developer."
image: "https://cdn.martincostello.com/blog_web-cpu.png"
---

> _This blog post was originally published by me on the [Just Eat Tech blog][original-post]._

## Introduction

[ASP.NET Core 2.1][aspnet-core-21] was released by Microsoft at the end of May, and last week we deployed
two consumer-facing applications upgraded to use ASP.NET Core 2.1 to production for the first time.

These applications have now been run in production for an entire weekend of peak traffic, and we’ve seen
some great performance improvements – in some cases **improving average response times by over 40%**.

<!--more-->

## Background

{{< cdn-image path="aspnet-core-releases.png" title="ASP.NET Core release timeline" >}}

.NET Core 1.0 was released almost 2 years ago now, [in June 2016][dotnet-core-10], and we’ve been using it
in some form or another at Just Eat ever since. Early adoption was rather limited amongst our teams, mainly
due to the fact that .NET Standard support had not yet filtered through to many of the third-party dependencies
we use to build our applications, such as the AWS SDK and NUnit, coupled with some APIs that are available in
the full .NET Framework not being available in the first release.

.NET Core 2.0 was subsequently [released in August last year][dotnet-core-20], adding many more APIs and
features, plus applying feedback Microsoft received since the first release from user feedback for real-world
application use-cases.

Since the release of .NET Core 2.0, more and more teams here at Just Eat have been adopting .NET Core for new
projects, plus a small number of refactors and/or rewrites of existing applications. At the time of writing, we
have over 40 different applications (we call these _features_ here at Just Eat) running in our production environments.

In fact, our internal Just Eat technology radar has recently moved .NET Core from a _Trial_ technology to an _Adopt_ technology.

## Upgrades

Since the [first preview of .NET Core 2.1]p[dotnet-core-21-preview-1] was made available in February, the team
I work in had been maintaining a branch of two of our ASP.NET Core 2.0 features. One is a consumer-facing MVC
web application that serves HTML pages using Razor, and the other is an MVC API application that acts as an
orchestration layer between that web application and a number of our lower-level internal APIs, all of which
run on .NET Framework 4.6.x.

A simple illustration of how these applications fit together in our architecture is shown below.

{{< cdn-image path="aws-network.png" title="Application architecture" >}}

From Previews 1 and 2 to Release Candidate 1, we experimented with various new features and capabilities, as
well as finding a few [bugs][bugs], [feature gaps][feature-gaps] and other changes along the way.

While upgrading from 2.0 to 2.1 is relatively simple if you just follow the [migration guide][migration-guide], some
of the more interesting additions in .NET Core 2.1 require additional development effort to leverage in your existing
application.

One new feature we found particularly interesting was [HttpClientFactory][httpclientfactory]. Given that both
applications perform many HTTP requests as part of their daily operations, particularly the orchestration API, we
felt that using the factory and the underlying `HttpClient` changes to use a managed Sockets implementation would
give us some good performance improvements.

Another was the new [`WebApplicationFactory<T>`][webapplicationfactory] class, which allowed us to delete lots of
boilerplate code in our test suite and make our integration tests faster and easier to set up. Overall the Git
patches for the two applications to go from 2.0 to 2.1 were `+199/−194` lines for the web application, and
`+246/−297` lines for the API.

Once the final ASP.NET 2.1 release was available (it was even released a [few days early][dotnet-core-21-early-access]
with some caveats) and we updated our Amazon Machine Image to have the ASP.NET Core 2.1 Runtime pre-installed, we
were ready to deploy the upgrade.

First we deployed the 2.1 build of the API to production as a canary (serving a small percentage of traffic) on
Monday 4th June and eagerly awaited the outcome. The morning after showed no errors or degraded performance, so
we promoted the canary to 100% of traffic for that feature on the Tuesday morning. Once that was done, we deployed
the 2.1 build of the web application to production for another over overnight canary. Again, no detriment was seen
in our logs and monitoring, so we promoted that to 100% of user traffic on the morning of the 6th June.

At Just Eat our busiest times for traffic are typically in the evenings, particularly at the weekend, so the real
litmus test for the new versions of both applications targeting ASP.NET Core 2.1 would be their first weekend serving
requests to hungry users of our UK consumer website.

On Monday 11th June I came into the office eager to pore over the results of the weekends’ traffic, and I was not
disappointed with the results at all!

## Show Me The Numbers

The comparisons below are for both applications running ASP.NET Core 2.1 on Sunday 10/06/18 compared to the previous
Sunday (03/06/18) where it was running ASP.NET Core 2.0. Sunday was chosen as the basis for comparison (rather than
Friday or Saturday) as traffic levels from users was the most similar week-to-week.

The response statistics were collected using [statsd][statsd] from either a middleware or `HttpMessageHandler` depending
on the context, which are pushed to Graphite and then visualised with Grafana.

CPU statistics are collected separately on our AWS EC2 instances and also pushed to Graphite.

### API

{{< cdn-image path="api-response-times.png" title="Response times from the API between 16:30 and 22:30 on 10/06/18 with a -7 day timeshift." >}}

The API averaged `107ms` per HTTP GET compared to `141ms` (**approximately a 24% improvement**), with much less deviation.

{{< cdn-image path="api-requests.png" title="Request rate to the API between 16:30 and 22:30 on 10/06/18 with a -7 day timeshift." >}}

The overall request rate also is shown to give an indication of the relative load on the API.

{{< cdn-image path="api-cpu.png" title="CPU utilisation of the API between 16:30 and 22:30 on 10/06/18 with a -7 day timeshift." >}}

CPU usage is also illustrated, which again shows a slight overall improvement. The spike in the graph is where auto-scaling
increased the number of EC2 instances in service based on average CPU load.

### Web

{{< cdn-image path="web-response-times.png" title="Response times from the web application between 16:30 and 22:30 on 10/06/18 with a -7 day timeshift." >}}

The web app averaged `130ms` per page render compared to `230ms` (**approximately a 43% improvement**) which again shows
much less deviation.

{{< cdn-image path="web-requests.png" title="Request rate to the web application between 16:30 and 22:30 on 10/06/18 with a -7 day timeshift." >}}

The overall request rate also is shown to give an indication of the relative load on the web application.

{{< cdn-image path="web-cpu.png" title="CPU utilisation of the website between 16:30 and 22:30 on 10/06/18 with a -7 day timeshift." >}}

CPU usage is also illustrated, which again shows a slight overall improvement. The spikes in the graph is where auto-scaling
increased the number of EC2 instances in service based on average CPU load.

Scaling occurs later than the previous week as the application instances are now able to handle more load per-box
for the same average CPU usage. This gives an additional benefit beyond performance, as it can reduce the overall
cost of running the application as the total provisioned CPU required for like-for-like load means that less EC2
instances are required.

The second spike on the CPU graph is caused by raised CPU as the application is installed onto the new EC2 instances coming
into service, which skews the CPU average temporarily.

## Conclusion

The performance improvements made by Microsoft and the Open Source community in ASP.NET Core and the Core CLR are
non-trivial. With minimal code changes you can super-charge your existing ASP.NET Core 2.0 applications by upgrading
to ASP.NET Core 2.1. This can reduce your response times to make your users’ experience better, and reduce your CPU
utilisation to help make your hosting bills lower too!

[aspnet-core-21]: https://devblogs.microsoft.com/dotnet/asp-net-core-2-1-0-now-available/
[bugs]: https://github.com/dotnet/sdk/issues/9367
[dotnet-core-10]: https://devblogs.microsoft.com/dotnet/announcing-net-core-1-0/
[dotnet-core-20]: https://devblogs.microsoft.com/dotnet/announcing-net-core-2-0/
[dotnet-core-21-early-access]: https://github.com/dotnet/aspnetcore/wiki/2.1.0-Early-Access-Downloads/e8f44a2b58299a1bc500b51baca5afb8d696f0f0
[dotnet-core-21-preview-1]: https://devblogs.microsoft.com/dotnet/announcing-net-core-2-1-preview-1/
[feature-gaps]: https://github.com/aspnet/Mvc/issues/7635
[httpclientfactory]: https://github.com/aspnet/HttpClientFactory
[migration-guide]: https://learn.microsoft.com/aspnet/core/migration/20_21
[original-post]: https://web.archive.org/web/20240422072816/https://tech.justeattakeaway.com/2018/06/14/aspnet-core-21-supercharging-our-applications/
[statsd]: https://github.com/justeattakeaway/JustEat.StatsD
[webapplicationfactory]: https://www.hanselman.com/blog/easier-functional-and-integration-testing-of-aspnet-core-applications
