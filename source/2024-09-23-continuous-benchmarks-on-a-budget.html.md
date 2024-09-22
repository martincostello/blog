---
title: "Continuous Benchmarks on a Budget"
date: 2024-09-23
tags: actions,benchmarks,benchmarkdotnet,ci,dotnet,github
layout: bloglayout
description: "Using GitHub Actions, GitHub Pages and Blazor to run and visualise continuous benchmarks with BenchmarkDotNet with zero hosting costs."
image: "https://cdn.martincostello.com/blog_benchmarks-regression.png"
---

<img class="img-fluid mx-auto d-block"
     src="https://cdn.martincostello.com/blog_benchmarks-regression.png"
     alt="A chart showing a time series for performance and memory usage with an increase in memory usage in the most recent data points"
     title="A chart showing a time series for performance and memory usage with an increase in memory usage in the most recent data points">

Over the last few months I've been doing a bunch of testing with the [new OpenAPI support in .NET 9][openapi-post].
As part of that testing, I wanted to take a look at how the performance of the new libraries compared to the existing
open source libraries for OpenAPI support in .NET, the most popular including [NSwag][nswag] and [Swashbuckle.AspNetCore][swashbuckle].

It's fairly easy to get up and running writing some benchmarks using [BenchmarkDotNet][benchmarkdotnet], but it's often a task
that you need to sit down and do manually when you have the need, and then gets forgotten about as time goes on. Because of that,
I thought it would be a fun mini-project to set up some automation to run the benchmarks on a continuous basis so that I could
monitor the performance of my open source projects easily going forwards.

In this post I'll cover how I went about setting up a continuous benchmarking pipeline using GitHub Actions, [GitHub Pages][github-pages]
and [Blazor][blazor] to run and visualise the results of the benchmarks on a "good enough" basis without needing to spend any money on infrastructure.

READMORE

## The Ideal

In an ideal world, we'd all have access to a dedicated performance lab, with a number of dedicated high-specification physical
machines that we could use to run benchmarks on a regular basis. We could generate reams of data from these benchmarks, and then
ingest that data into a data warehousing solution and run reports, generate dashboards and much more to monitor performance metrics
for the software we're building.

The .NET team is an engineering team with the budget for such a setup, and they have engineers dedicated to performance testing
and the supporting infrastructure needed to run them. For example they have a [Power BI dashboard][aspnetcore-benchmarks-dashboard]
that they use to track the performance of the ASP.NET Core framework over time using dozens of benchmarks for [ASP.NET Core][aspnetcore-benchmarks-code]
and the [.NET Runtime][dotnet-performance] that the product engineers can use to test the impact of changes they make, and are run
on a regular basis to identify regressions. You can read more about their [benchmarks][aspnetcore-benchmarks-docs] in GitHub.

As great as that would be, we're not all the likes of Microsoft, especially in the open source world. I don't know about you, but
I certainly don't have the budget to maintain a dedicated performance lab, physical or virtual, and a data warehouse to run on top
of it. How can we as open source software developers leverage the free tools available to us today to achieve something similar in
spirit to an Enterprise-level solution that still gives us value?

## Prior Art and Inspiration

I'm a big fan of GitHub Actions, and use it for all of my own software projects to build and deploy my software, as well to automate
other tasks like [applying monthly .NET SDK updates][dotnet-automated-patching] or housekeeping tasks like [clearing out old Azure Container Registry images][acr-housekeeping].
GitHub Actions also comes with a generous free tier for public repositories - at the time of writing you get unlimited minutes for
running GitHub Actions workflows, capped at [20 concurrent jobs][github-actions-limits] for Linux and Windows runners (macOS is less
generous, at 5).

GitHub Actions isn't ever going to be a like-for-like replacement for dedicated performance machines, especially on the free tier
rather than with custom dedicated runners, but it's a great alternative. We can't rely on these runners to give us accurate _absolute_
benchmark results (i.e. how fast can my code possibly ever go), but we can use them to give us good _relative_ benchmark results to
produce trends over time. There will still be an element of noise in the results due to the shared nature of the runners because
we have no control over the underlying hardware they run on, so they may change unexpectedly over time as the service is upgraded,
but that's a trade-off that can be balanced against the usefulness of such a architecture for a _"budget performance lab"_.

Given that, my first thought was that someone must have already written a GitHub Action to run benchmarks and collect the data for
them. Indeed, that was the case and the action I found that ended up being a major source of inspiration for my own setup was the
[benchmark-action/github-action-benchmark][publisher-inspiration] action.

The action supports 10 existing performance testing tools, including BenchmarkDotNet for .NET, other tools for Go, Java and Python,
as well as custom tools. The action ingests the output of these tools, summarises the results into a JSON document, and then
pushes the results into a GitHub repository. It also commits static assets like HTML, CSS and JavaScript files to the repository
alongside the results so that you can view the results in a web browser. The static pages include charts generated using [Chart.js][chart-js]
so that you can view trends in the data over time and spot regressions. The action can also be configured to comment on pull requests
or commits if it determines that a regression has occurred in the benchmark data, removing some of the burden of needing to watch
for changes by eye.

By setting up a [GitHub Pages][github-pages] site to serve a website for the content of the repository, you can use the static HTML
files to visualise the results of the benchmarks in a web browser. GitHub Pages is free to use, so using a public GitHub repository (free)
to store the data in conjunction with GitHub Pages to view the results (free) and GitHub Actions to run BenchmarkDotNet to generate the
results (free), you can see how we've got all the pieces in place to host a continuous benchmarking solution without needing a budget for
any hardware or infrastructure.

## The Solution

OK, so if there's already an action do to all of this, why did I go and write my own version of it? While the existing action is great,
because it's focused on multiple different tools, there's an element of _least common denominator_ to the features it has. The key feature
that it lacked for BenchmarkDotNet was the ability to visualise memory allocations in the charts in addition to the time/duration for
the benchmarks. There were also a number of minor other things I wanted to be able to do that the existing action didn't support out-of-the-box,
like customing Git commit details.

While the UI it provides by default is functional, and it's possible to create your own custom UI to visualise the data, the JavaScript to
generate the dashboards hasn't really been designed with testability and extensibility in mind (in my opinion). As I started to customise
the provided code over a week or so to meet my needs, I found I was often breaking it with unintentional regressions, and it was difficult
to test in the form it's provided in by default.

With that in mind, I decided I would create my own fork-in-spirit of the original action, but with a focus on BenchmarkDotNet. This would
allow me to customise the UI to my needs, and to make it more testable and extensible in the future. Also, a new side-project is always a
fun excuse to learn some new technology!

### Generating the Benchmarks

TODO

### Storing the Data

TODO

### Visualising the Data

TODO

## Concrete Results

TODO

## Summary

TODO

[acr-housekeeping]: https://github.com/martincostello/github-automation/blob/adf8d8b14b6b8ac7be8ca8f30614ac4dfb137642/.github/workflows/acr-housekeeping.yml "A GitHub Actions workflow to clean up ACR images"
[aspnetcore-benchmarks-dashboard]: https://aka.ms/aspnet/benchmarks "ASP.NET Core Benchmarks Power BI Dashboard"
[aspnetcore-benchmarks-code]: https://github.com/aspnet/Benchmarks "ASP.NET Core Benchmarks on GitHub"
[aspnetcore-benchmarks-docs]: https://github.com/dotnet/aspnetcore/blob/main/docs/Benchmarks.md "ASP.NET Core Benchmarks on GitHub"
[benchmarkdotnet]: https://github.com/dotnet/BenchmarkDotNet "The BenchmarkDotNet repository on GitHub"
[benchmarks-data]: https://github.com/martincostello/benchmarks "Benchmarks data repository on GitHub"
[benchmarks-demo]: https://github.com/martincostello/benchmarks-demo "Benchmarks demo repository on GitHub"
[benchmarks-site]: https://benchmarks.martincostello.com/ "Benchmarks dashboard deployed to GitHub Pages"
[blazor]: https://learn.microsoft.com/aspnet/core/blazor/ "ASP.NET Core Blazor"
[bunit]: https://bunit.dev/ "bUnit: a testing library for Blazor components"
[chart-js]: https://www.chartjs.org "Chart.js Website"
[dotnet-aspire]: https://learn.microsoft.com/dotnet/aspire/ ".NET Aspire documentation"
[dotnet-automated-patching]: https://www.youtube.com/live/pOeT1otTi4M?si=9OEq-rm_DTopVNd1&t=172 "On .NET Live - Effortless .NET updates with GitHub Actions"
[dotnet-performance]: https://github.com/dotnet/performance "The dotnet/performance repository on GitHub"
[github-actions-limits]: https://docs.github.com/en/actions/administering-github-actions/usage-limits-billing-and-administration "GitHub Actions usage limits, billing and administration"
[github-pages]: https://pages.github.com/ "GitHub Pages"
[nswag]: https://github.com/RicoSuter/NSwag "The NSwag repository on GitHub"
[openapi-post]: https://blog.martincostello.com/whats-new-for-openapi-with-dotnet-9/ "What's New for OpenAPI with .NET 9"
[plotly]: https://plotly.com/javascript/ "Plotly JavaScript Open Source Graphing Library"
[power-bi]: https://learn.microsoft.com/power-bi/fundamentals/power-bi-overview "What is Power BI?"
[publisher-action]: https://github.com/martincostello/benchmarkdotnet-results-publisher "The benchmarkdotnet-results-publisher repository on GitHub"
[publisher-inspiration]: https://github.com/benchmark-action/github-action-benchmark "The github-action-benchmark repository on GitHub"
[regression-comment]: https://github.com/martincostello/project-euler/pull/335#issuecomment-2302688319 "Example of a comment on a pull request for a regression"
[runtime-regression]: https://github.com/dotnet/runtime/issues/107869 "Performance regression with JsonObject creation by +70%"
[swashbuckle]: https://github.com/domaindrivendev/Swashbuckle.AspNetCore "The Swashbuckle.AspNetCore repository on GitHub"

<!--
https://cdn.martincostello.com/blog_benchmarks-regression.png
https://cdn.martincostello.com/blog_benchmarks-regression-tooltip.png
-->
