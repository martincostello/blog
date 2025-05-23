---
title: Reliably Testing HTTP Integrations in a .NET Application
date: 2017-10-03
tags: testing,httpclient
layout: bloglayout
description: "Reliably testing your HTTP API integrations in .NET with JustEat.HttpClientInterception to intercept your HTTP calls to use custom HTTP responses."
---

Over the past few months I've been working on some new ASP.NET Core applications in my day job at Just Eat, and as part of that devised a new strategy for integration testing the applications with respect to their HTTP dependencies.

I've written a blog post about the problems I faced and how I went about solving them [here](https://blog.martincostello.com/reliably-testing-http-integrations-in-dotnet-applications/ "Read the post here").

READMORE

You can also find `JustEat.HttpClientInterception`, the open-source library written by me as part of the solution, on both [GitHub.com](https://github.com/justeat/httpclient-interception "JustEat.HttpClientInterception on GitHub.com") and [NuGet.org](https://www.nuget.org/packages/JustEat.HttpClientInterception/ "JustEat.HttpClientInterception on NuGet.org").
