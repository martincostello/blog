---
title: Improving ASP.NET Core Before It Ships 🚢
date: 2022-05-03
tags: dotnet,aspnetcore,performance
layout: bloglayout
description: "Upgrading to ASP.NET Core 2.1 can deliver serious performance improvements to your web applications as well as make you much more productive as a developer."
image: "https://cdn.martincostello.com/blog_deadlock-parallel-stacks.webp"
---

> _This blog post was originally published by me on the [Just Eat Tech blog][original-post]._

Writing code that millions of people will use is something we do every day. Writing code that millions
of developers will use feels a little different.

Have you ever wondered why Microsoft releases preview versions of their products before the final release?
Well, it’s so real customers can help ensure their quality before they go live. In this post, we’ll tell you
about an issue we found in testing the previews of ASP.NET Core and how we worked with Microsoft to fix it.

READMORE

## Early-Adoption and Release Candidates 🌅🐣

We’re big fans of open-source software, and like to give back to the community wherever we can. For the past
few years, we’ve tested upcoming features of new .NET releases with the previews. This lets us experiment with
new features and provide feedback to the .NET team. It also helps verify new features with our use cases, and
adds an example of real-world code to help catch bugs.

[_ASP.NET Core Minimal APIs_][minimal-apis], new in .NET 6, provide a simpler application model compared to
[ASP.NET Core MVC][mvc]. It looked interesting for simplifying some of our API applications. We tested it with
some APIs in our dev environments and got great results using less code. We also found several issues with the
previews which got fixed, and we even got to [implement a feature][feature].

Microsoft supports preview versions when they become Release Candidates. Once [.NET 6 RC1][dotnet-6-rc1]
landed we started to roll out the changes to production where it would serve traffic from our customers.

We use [_Canary Releases_][canary] as part of our continuous deployment strategy. This lets us incrementally roll
out changes, reducing the risks from software change. If anything goes wrong, we can roll back to the last version
of an application within a few minutes. We started with one application and deployed it on a Monday (the day we see
the least production traffic). We left it in [_"canary"_][canary] 🐤 for a 24-hour period for 25% of our production
traffic. After promoting to 100% of traffic the next day, we repeated this with some other applications. Within a
week we were running some EC2 and Lambda workloads on .NET 6 RC1.

Our deployments were fine so when [.NET 6 RC2][dotnet-6-rc2] was announced we updated and increased the rollout
to more apps. Once again, everything went fine with no problems. We were ready to update to the final .NET 6
release in November.

## Deadlock! 💀🔒

The [stable release of .NET 6][dotnet-6-rtm] was out in November 2021. We updated our applications running the
release candidate to the stable release. Everything was fine, with no issues. The job was done, or so it seemed.

Over lunchtime a few hours later, one of our updated applications using Minimal APIs ground to a halt. Our alerting
system fired up and paged the on-call engineer for the application. Clients of the application were getting HTTP
request timeouts and HTTP 503 errors. Looking at the AWS Auto Scaling Group for the app showed that there appeared
to be no healthy instances. This seemed like a classic case of an application [deadlock][deadlock].

<img class="img-fluid mx-auto d-block"
     src="https://cdn.martincostello.com/blog_deadlock-request-rate.webp"
     alt="HTTP requests to the application when the deadlock occurred."
     title="HTTP requests to the application when the deadlock occurred.">

We deployed the changes hours ago and had no issues with previews and all our tests had passed — what had gone wrong?
🤔 The app self-healed within 20 minutes, we rolled back to the old version and started to look into it.

## Replicating the Issue 🔁

The first step in diagnosing the root cause was to diff the code between the two releases. The only changes between
the two versions were to update the .NET 6 NuGet package versions (e.g. `6.0.0-rc.2.21480.5` to `6.0.0`). This meant
that the root cause must be from a change in .NET between Release Candidate 2 and the stable release.

The clue that pointed us to where to look further for the problem was from our metrics. They showed that the application
reloaded its configuration right before the alerts fired.

We use [Hashicorp Consul][consul] to store configuration that we wish to change at runtime. This lets us change things
like feature toggles without needing to deploy a code change. Settings change either in the code within Git for a
permanent change, or in the Consul UI for a temporary change. Configuration resets to what is in the code every day at
0800, so temporary changes are reverted the following day.

When config changes in Consul, an agent installed on each EC2 instance gets notified. The agent makes an HTTP request
to the app installed on it to an endpoint we use to reload the config. In the case of our .NET apps, we use the
[`IConfigurationRoot.Reload()`][reload] method to do this, similar to what is shown below:

```
app.MapPost("/configuration/reload", (IConfiguration config) =>
{
    if (config is IConfigurationRoot root)
    {
        root.Reload();
    }
    return Results.NoContent();
});
```

Our configuration splits into _environment_ settings and _application_ settings. In this case, another team had changed
an _environment_ setting which made the shared config reload. The _application_ config hadn’t changed, so there wasn’t
an obvious cause-and-effect from the config change to the incident.

This gave us the hypothesis that when the application’s config reloads under high load it would go into a deadlock.

With this hypothesis, the next step was to try and replicate the issue. We turned to our staging environment, which is an
AWS environment that is as close to our production AWS environment as possible. All changes must pass through Staging
before they roll out into production.

Staging also has _"synthetic load"_ running against it. This is artificial user traffic that is always running against
our apps and infrastructure deployed there.

We re-deployed the version of the application with the issue to our staging environment (with synthetic load running).
We then wrote a small shell script that called the config reload endpoint in a loop to see what would happen. Within
15 minutes the script reloading the config ground to a halt and then started timing out. Success, we had replicated
the problem!

## Diagnosing the Problem 🕵️

The next step was to get a better understanding of exactly what was causing the deadlock. With the app still running
(but deadlocked) in Staging, we used [_Process Explorer_][process-explorer] to capture a memory dump of the process
which hosts the app. Once we had a memory dump we could use [Visual Studio to debug it][debug-dump-files].

Opening the memory dump in Visual Studio with [_Just My Code_][just-my-code] disabled lets see the state of the
application via the [_Parallel Stacks_][parallel-stacks] window. The problem was easy to spot — the graph of the
stacks showed that one stack was waiting on another stack and vice-versa (there were even some helpful 🛑 icons).

Threads for user requests were waiting on a lock held by reloading the config. The thread reloading the config was
waiting on a lock held by those user requests. Here was our deadlock!

<img class="img-fluid mx-auto d-block"
     src="https://cdn.martincostello.com/blog_deadlock-parallel-stacks.webp"
     alt="The parallel stacks in the deadlocked application."
     title="The parallel stacks in the deadlocked application.">

## Getting the Bug Fixed 🐛🔧

Having determined that this deadlock was within .NET itself, we’d need to create an issue in the relevant
[dotnet GitHub repo][dotnet-runtime]. Bugs are always easier to fix when you have a
[_Minimal Reproducible Example_][minimal-repro], so we needed to provide one.

We created a self-contained GitHub repo that would [reproduce the bug][repro] we had encountered and then
[opened an issue][issue] for the .NET team to look into. The .NET engineer assigned to the issue, [Stephen Halter][stefan-halter]
from the ASP.NET Core team, was very helpful in getting to the bottom of the problem and [fixing it][fix].
We were also given a [work-around][workaround] so we could _fix-forward_ and re-deploy the stable .NET 6.0 release.

The fix wasn’t trivial, so there was a lot of caution and due diligence on the part of the .NET team in
validating it. Not only did they need to check to make sure it had fixed the bug, but also that it did not
introduce any new issues. We helped by validating the fix in Staging using a nightly build of ASP.NET Core
that contained the fix before it merged to the `release/6.0` branch. We ran the same tests and we could no
longer replicate the original problem, nor had any new issues. 🎉

The fix was available as part of the [.NET 6.0.3][dotnet-6.0.3] servicing release in March 2022. After
upgrading the apps to the new version, we were able to remove the workaround and tidy up our code. Now
we’re running ASP.NET Core 6 Minimal APIs in production apps at scale (over 45,000 requests/minute at peak)
with no issues and less boilerplate. 🖥️🚀

## Key Takeaways 🔑🥡

This blog post is a short tale of some of our adventures using previews of open-source software at scale in
production. Here are a few key points to take away from this post:

- A healthy open source software community includes publishers and consumers that collaborate. If you consume open source software, particularly for free, consider if there are ways you can contribute back. This might be in the form of feature requests, filling bugs, or contributing code changes. Working together in public makes open source software better for everyone who uses it.
- Observability of applications is as important as their functionality — if something goes wrong they can help you get back to a good state. They can also give you vital clues and insights into how to prevent it from happening again.
- Keep your deployable units small. Practicing [continuous delivery][cd] makes it easier to get to the root of a problem when something goes wrong.
- Know your debugging tools and how you can leverage them in production. Knowing the tools at your disposal to use with production code as much as with local development pays dividends. Having a range of tools you are familiar with that you can bring to an incident can get you to a root cause faster.
- Developers love minimal reproducible examples! When logging a bug you can help get it triaged sooner by giving an isolated sample for the maintainers to look at. This will then help the project develop a fix and make it available in a shorter period than a vague bug report might.

We hope this post inspires you to try out the latest releases of your own favourite open-source software projects.
How about trying out the latest [.NET 7 preview release][dotnet-7-preview3] with one of your own .NET applications?

Happy coding!

[canary]: https://martinfowler.com/bliki/CanaryRelease.html
[cd]: https://continuousdelivery.com/
[consul]: https://developer.hashicorp.com/consul
[deadlock]: https://en.wikipedia.org/wiki/Deadlock_(computer_science)
[debug-dump-files]: https://learn.microsoft.com/visualstudio/debugger/using-dump-files
[dotnet-6.0.3]: https://github.com/dotnet/core/blob/main/release-notes/6.0/6.0.3/6.0.3.md
[dotnet-6-rc1]: https://devblogs.microsoft.com/dotnet/announcing-net-6-release-candidate-1/
[dotnet-6-rc2]: https://devblogs.microsoft.com/dotnet/announcing-net-6-release-candidate-2/
[dotnet-6-rtm]: https://devblogs.microsoft.com/dotnet/announcing-net-6/
[dotnet-7-preview3]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-7-preview-3/
[dotnet-runtime]: https://github.com/dotnet/runtime
[feature]: https://devblogs.microsoft.com/dotnet/asp-net-core-updates-in-net-6-preview-7/#support-request-response-and-user-for-minimal-actions
[fix]: https://github.com/dotnet/runtime/pull/63816
[issue]: https://github.com/dotnet/runtime/issues/61747
[just-my-code]: https://learn.microsoft.com/visualstudio/debugger/just-my-code
[minimal-apis]: https://learn.microsoft.com/aspnet/core/fundamentals/minimal-apis
[minimal-repro]: https://en.wikipedia.org/wiki/Minimal_reproducible_example
[mvc]: https://learn.microsoft.com/aspnet/core/mvc/overview
[original-post]: https://medium.com/justeattakeaway-tech/improving-asp-net-core-before-it-ships-3e44b6f65054
[parallel-stacks]: https://learn.microsoft.com/visualstudio/debugger/using-the-parallel-stacks-window
[process-explorer]: https://learn.microsoft.com/sysinternals/downloads/process-explorer
[reload]: https://learn.microsoft.com/dotnet/api/microsoft.extensions.configuration.iconfigurationroot.reload
[repro]: https://github.com/martincostello/ConfigurationManagerDeadlock#readme
[stefan-halter]: https://github.com/halter73
[workaround]: https://github.com/dotnet/runtime/issues/61747#issuecomment-973164180
