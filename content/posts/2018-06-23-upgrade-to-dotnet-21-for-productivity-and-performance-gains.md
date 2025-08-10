---
title: Upgrade to ASP.NET Core 2.1 for Productivity and Performance Gains
date: 2018-06-23
tags: dotnet,aspnetcore,performance
layout: post
description: "Upgrading to ASP.NET Core 2.1 can deliver serious performance improvements to your web applications as well as make you much more productive as a developer."
---

I've been using .NET Core since it was released [back in June 2016](https://blogs.msdn.microsoft.com/dotnet/2016/06/27/announcing-net-core-1-0/ "Announcing .NET Core 1.0") as my development technology of choice for my personal projects, as well as helping introduce it as a mainstream technology choice at [Just Eat](https://www.just-eat.com/ "Just Eat global website") (my employer at the time of writing).

I find it so much more pleasurable to code against compared to "traditional" ASP.NET. With features such as self-hosting, built-in dependency injection and a high level of testability, you can really focus on solving the domain problem at hand, rather than worrying too much over boilerplate and ceremony.

Each new release, both major and minor, brings something new to geek-out over, but ASP.NET Core 2.1 has been a particular stand-out so far for new features and benefits that I find really compelling as a software developer.

<!--more-->

In this blog post I'm going to cover two aspects of software development ASP.NET Core 2.1 really helps with — _productivity_ and _performance_ — with some specific examples from my personal side projects as well as professionally at my day job.

Below are links to the two GitHub Pull Requests to the repositories for my personal websites to upgrade them to ASP.NET Core 2.1:

- [Update to ASP.NET Core 2.1](https://github.com/martincostello/website/pull/211 "Update to ASP.NET Core 2.1") for martincostello.com
- [Migrate to ASP.NET Core 2.1](https://github.com/martincostello/alexa-london-travel-site/pull/178 "Migrate to ASP.NET Core 2.1") for londontravel.martincostello.com

## Productivity Improvements

Developers like to be productive. Whether that's writing code faster, using less keystrokes, or automating tests. We'd all like to be coding and doing the parts of software development we enjoy, rather than the more mundane parts.

For me there's two new features in .NET Core 2.1 that really make the kind of software I generally work on, integrating applications with REST APIs, much easier to get on with than before. The first of these is [HttpClientFactory](https://github.com/aspnet/HttpClientFactory "aspnet/HttpClientFactory on GitHub.com").

### HttpClientFactory

HttpClientFactory provides a number of APIs that build on top of `HttpClient` to make it easier to solve common cross-cutting concerns for HTTP API integrations, such as configuration, resiliency and scalability.

The dependency injection extension methods make it easier to setup named `HttpClient` instances for your dependencies with appropriate configuration applied (base addresses, timeouts, request headers etc.). This can also be combined with typed clients, such as those provided by [Refit](https://github.com/reactiveui/refit "Refit on GitHub.com"), to let you abstract away a lot of the low-level interaction of HTTP APIs and get on with the business of writing integrations with your dependencies.

HttpClientFactory also ships an [extensions library](https://www.nuget.org/packages/Microsoft.Extensions.Http.Polly/ "Microsoft.Extensions.Http.Polly on nuget.org") that allow you to easily integrate [Polly](https://github.com/App-vNext/Polly "Polly on GitHub.com") into your HTTP requests to add behaviours such as retries for transient HTTP errors and circuit-breakers.

Another feature of HttpClientFactory is that it pools the underlying HTTP connections, meaning that system networking resources are managed for you, so your application gets the best performance it can by transparently re-using socket connections and handling things like DNS TTLs.

The builder configuration API it has also makes it easy to plug in additional behaviours, like [HTTP request interception](https://github.com/justeat/httpclient-interception/tree/master/samples#httpclient-interception-samples "httpclient-interception samples on GitHub.com") for use with integration tests.

Here's some links to GitHub Pull Requests where I've updated some of the applications I maintain to use HttpClientFactory:

- [Update sample to use ASP.NET Core 2.1 and HttpClientFactory](https://github.com/justeat/httpclient-interception/pull/23 "Update sample to use ASP.NET Core 2.1 and HttpClientFactory for JustEat.HttpClientInterception")
- [Upgrade to ASP.NET Core 2.1](https://github.com/justeat/ApplePayJSSample/pull/27 "Upgrade to ASP.NET Core 2.1 for Apple Pay JS Sample code")

### WebApplicationFactory&lt;T&gt;

As I mentioned above, HttpClientFactory makes it easier to integration test your applications, but ASP.NET Core 2.1 ships with another new feature that makes it _even easier_ to integration test ASP.NET Core applications than in previous releases.

`WebApplicationFactory<T>`, which ships with the [Microsoft.AspNetCore.Mvc.Testing](https://www.nuget.org/packages/Microsoft.AspNetCore.Mvc.Testing/ "Microsoft.AspNetCore.Mvc.Testing on NuGet.org") NuGet package, provides a simple way to create a fixture to use in your integration tests that hosts your application in-memory. This allows you to integration test your MVC views and Razor pages without first deploying the application to a server like Kestrel or IIS.

You can even extend it to self-host your application on an HTTP or HTTPS port using Kestrel so you can run browser automation tests with technologies like [Selenium](https://github.com/SeleniumHQ/selenium "Selenium on GitHub.com").

For my [London Travel Alexa skill's](https://www.amazon.co.uk/dp/B01NB0T86R "London Travel on amazon.co.uk") [companion website](https://londontravel.martincostello.com/ "London Travel companion website"), I combined both HttpClientFactory's ability to configure interception of HTTP requests with self-hosting with `WebApplicationFactory<T>` to add browser integration tests for the OAuth identity integration for user accounts.

You can see the changes made to achieve this in [this Pull Request](https://github.com/martincostello/alexa-london-travel-site/pull/186 "Integrate HttpClientFactory for my Alexa skill companion website"). This PR increased the overall code coverage of the test suite by nearly 7%.

## Performance Improvements

Developer productivity increases are great because they make it easier for you to get on with writing software. The benefit to your users is less tangible though, with the gains mainly being with accelerating your work so you can deliver value to users at an increased pace.

Performance improvements on the other hand are a win for both the developer of an application as well as its users. Your users get an improved experience with benefits such as faster page load times, but as an application developer and maintainer you get the benefit of more bang-for-your-buck from your hardware.

This can translate into real tangible benefits such as reduced hosting costs if you host in the cloud, such as in Microsoft Azure or Amazon Web Services, as you can serve more requests for the same level of service provisioning.

In practice this could mean that as your application's usage increases you can scale out later, or for a constant level of load you could maybe scale down and reduce your instance sizes. Either scenario could result in a reduction in your monthly hosting bills.

Microsoft and the .NET community have really, _really_ put a focus into performance for ASP.NET Core 2.1. In fact, I'll let this tweet speak for itself from when I updated my website to ASP.NET Core 2.1 on the 31st of May after it was released.

<blockquote class="twitter-tweet" data-lang="en"><p lang="en" dir="ltr">Spot where I upgraded my website to <a href="https://twitter.com/hashtag/AspNetCore?src=hash&amp;ref_src=twsrc%5Etfw">#AspNetCore</a> 2.1 yesterday: <a href="https://t.co/m0rRgNL6G9">pic.twitter.com/m0rRgNL6G9</a></p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/1002463054511136768?ref_src=twsrc%5Etfw">June 1, 2018</a></blockquote>
<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>

That's quite the cliff of a response time improvement isn't it?

I've been surprised by the amount of attention this quick tweet gathered, with it even popping up in the [ASP.NET Community Standup](https://youtu.be/LCV-JNOkZC4?t=15m16s "ASP.NET Community Standup - June 5, 2018 - ASP.NET Core 2.1 Release Party @15:16") and the results of [round 16 the TechEmpower benchmarks](https://www.techempower.com/blog/2018/06/06/framework-benchmarks-round-16/ "
June 6, 2018
Framework Benchmarks Round 16").

Of course, improving the response times of the simple MVC application for my personal website isn't exactly a real-world example of an application under user load is it? The proof of the pudding is in the eating, so we updated two ASP.NET Core applications I maintain at work from 2.0 to 2.1 the week after, and the numbers involved there were just as impressive.

<blockquote class="twitter-tweet" data-conversation="none" data-lang="en"><p lang="en" dir="ltr">&quot;So what about a real production application&quot; I hear some of you ask? Well, here&#39;s some charts from UK production on Sunday evening for a <a href="https://twitter.com/aspnet?ref_src=twsrc%5Etfw">@aspnet</a> Core 2.1 web application and a 2.1 API application that it depends on.<br>TL;DR: Razor page rendering 43% faster, API 24% faster 🚀 <a href="https://t.co/KubMSxnLLF">pic.twitter.com/KubMSxnLLF</a></p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/1006177524848713728?ref_src=twsrc%5Etfw">June 11, 2018</a></blockquote>
<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>

Yes, you read that correctly. It improved the performance of an MVC web application by **43%**! Considering the changes were more-or-less just a framework upgrade (plus some new usage of HttpClientFactory), that's a tremendous improvement for the amount of development work put in to do the upgrade.

You can read more about the performance improvements in these two applications and how the metrics were collected in my post here: [ASP.NET Core 2.1 – Supercharging Our Applications 🚀](https://blog.martincostello.com/aspnet-core-21-supercharging-our-applications/ "ASP.NET Core 2.1 – Supercharging Our Applications")

It was nice to see good feedback from members of the .NET community on the blog post as well, including from [Ben Adams](https://twitter.com/ben_a_adams "Ben Adams on Twitter") of Illyriad, who is quite a prolific contributor to .NET Core for performance gains.

<blockquote class="twitter-tweet" data-lang="en"><p lang="en" dir="ltr">Nice to see <a href="https://twitter.com/justeat_tech?ref_src=twsrc%5Etfw">@justeat_tech</a> and their customers (myself included) getting great performance benefits from <a href="https://twitter.com/hashtag/aspnetcore?src=hash&amp;ref_src=twsrc%5Etfw">#aspnetcore</a> 2.1; because <a href="https://twitter.com/hashtag/perfmatters?src=hash&amp;ref_src=twsrc%5Etfw">#perfmatters</a> <a href="https://t.co/mz8gpekQYO">https://t.co/mz8gpekQYO</a> <a href="https://t.co/btT44mUW7x">pic.twitter.com/btT44mUW7x</a></p>&mdash; Ben Adams (@ben_a_adams) <a href="https://twitter.com/ben_a_adams/status/1009158831732125697?ref_src=twsrc%5Etfw">June 19, 2018</a></blockquote>
<script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>

## Conclusion

Microsoft and the open source community have really delivered a great release with ASP.NET Core 2.1, with [tonnes of new features and improvements](https://github.com/dotnet/core/blob/master/release-notes/2.1/2.1.0.md ".NET Core 2.1 Release Notes on GitHub.com"), of which I've just touched the surface with a couple of my favourites.

Performance is the hero of .NET Core 2.1 though, so if you maintain any ASP.NET Core applications, I'd strongly recommend that you upgrade to 2.1 as soon as you can to really supercharge your applications and make yourself, and your users, happier for it.

Here's to ASP.NET Core 2.2 and beyond! 🚀
