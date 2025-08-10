---
title: "Upgrading to .NET 8: Part 3 - Previews 1-5"
date: 2023-07-12
tags: dotnet,preview,upgrade
layout: post
description: "Highlights from upgrading to .NET 8 previews 1-5"
image: "https://cdn.martincostello.com/blog_dotnet-bot.png"
---

In the [previous post of this series][part-2] I described how with some
GitHub Actions workflows we can reduce the amount of manual work required
to test each preview of .NET 8 in our projects. With the infrastructure to
do that set up we can now dig into some highlights of the things we found
in our testing of .NET 8 itself in the preview releases available so far
this year!

READMORE

## Preview 1

[Preview 1][preview-1] as I've found in the past is often the way with the
first preview of a new major release, was pretty uneventful.

The first preview typically contains changes that were added after the previous
major release was branched and considered feature complete. There's usually not
yet much in the way of new features to try out. As such it's mostly just useful
as a first step for a new year of testing and to get things ready for the more
exciting changes that will come later in the release.

One item of note was that a number of new ASP.NET Core analysers were added
which broke a few of our builds (we typically use `TreatWarningsAsErrors=true`
for our builds). These were trivial to fix, but did identify two false positives
that we reported to the ASP.NET Core team ([1][dotnet-aspnetcore-46907],
[2][dotnet-aspnetcore-46936]).

These issues are a good example of why prerelease testing is useful to the .NET
product teams - it exposes their changes to a wider body of code and can help
identify patterns that might not have been considered as part of the tests
added when the changes were first made.

A final small (but important!) change was that the default port used for HTTP
for container images was [changed from `80` to `8080`][docker-port-change]. This
subtle change required us to update the port mappings in some of our Kubernetes
configuration files so that the application still worked correctly when deployed
to an [Elastic Kubernetes Service][eks] (EKS) cluster.

## Preview 2

An interesting new change added in [preview 2][preview-2], again for containers,
was the ability to run the application _["rootless"][rootless]_. By making a small
change to the `Dockerfile` for the application before the `ENTRYPOINT` is defined,
we can improve the default security of the application by running it as a non-root user
with `USER app`.

Otherwise the release was again pretty uneventful.

## Preview 3

[Preview 3][preview-3] was a little more interesting, with three interesting changes.

### Container User ID Environment Variable

The first relates to the previous change for containers, but changes it in a way that's
more secure than the previous way by using `USER $APP_UID`.

Instead of explicitly referencing the user by name, we can now use the `APP_UID`
environment variable that is explicitly set in the `Dockerfile` to the UID of the
`app` user. This avoids us having to hard-code a magic string - much nicer.

### Artifacts output

The second change was a new feature that allows [the output path for the build to be simplified][artifacts-output].
This opt-in change allows you to change from having a separate `bin` and `obj` folder
per project to instead have a single `artifacts` folder in the root of the solution
directory that instead contains all of the output from your build and publish steps.

<img class="img-fluid mx-auto d-block w-75" src="https://cdn.martincostello.com/blog_artifacts-output.png" alt="The new artifacts output folder" title="The new artifacts output folder">

This makes it much easier to find the output artifacts for your application and
process them, such as when deploying an application to a remote server or
publishing a NuGet package. Trying this out did uncover an issue in for library
library repositories though - [there was a bug][dotnet-sdk-31882] where if you
had enabled [NuGet package validation][nuget-validation] then `dotnet pack` would fail.

I have this enabled in all my library projects, so I put testing this feature on hold until preview 4
as otherwise all of their continuous integration builds fail 😅.

### Request Delegate Generator

_[Minimal APIs][minimal-apis]_, which were introduced in .NET 6, are a great way to write simple
HTTP API endpoints using Lambda expressions without the overhead and ceremony of an MVC Controller
and Actions.

For example, you could write a simple API endpoint to return the current time as JSON like this:

`app.MapGet("/now", () => new { utcNow = DateTimeOffset.UtcNow });`

<!--
<script src="https://gist.github.com/martincostello/0d8accf83aa985c490aa5870a21216de.js"></script>
-->

The way this is implemented at runtime though is that additional code is compiled when the applicaion
starts up to provide the "glue" that wires up the Lambda expression to the HTTP request pipeline.

However, when an application is using [Ahead-of-Time (AOT) compilation][aot], this additional code
cannot be compiled as the infrastructure to do so cannot be used. This creates a dilemma - how do we
use Minimal APIs and Native AOT compilation together?

The answer to this is the new _[Request Delegate Generator][rdg]_ (RDG).

This changes the way that Minimal APIs are implemented so that the additional code that is required
is instead compiled into the application at build time using a [Roslyn Source Generator][source-generator].
By moving the compilation to build time this side-steps the restrictions of AOT compilation, but it
_also_ increases the start-up time performance of an application not using AOT because the work no longer
needs to be done at runtime.

Many applications I'm testing .NET 8 with are using Minimal APIs, but they also use functionality that
isn't going to be supported by .NET AOT in the .NET 8 release, such as Razor Pages. Support is for these
scenarios is likely to come in either .NET 9 (🤞) or .NET 10.

As it is still useful for applications without native AOT, I thought it would still be good to try out.
This lead to the [first issue I found with RDG][dotnet-aspnetcore-47202] - there was some scenarios that
still weren't supported that lead to the compiler throwing an exception. Let's revisit RDG in preview 4.

## Preview 4

With the release of [preview 4][preview-4] there was a bunch of new shiny things to play with, as well as
fixes for some of the issues I'd found in preview 3. Let's take a look at some of the highlights.

### Request Delegate Generator (redux)

With the initial blocking issue I found fixed, I re-enabled the support for it in a number of different projects.
For the majority of them there were no issues, but some of them did flush out a number of edge cases:

- [RequestDelegateGenerator fails to detect route pattern that uses a constant for its value][dotnet-aspnetcore-48307]
- [Request Delegate Generator code counts as user code for code coverage][dotnet-aspnetcore-48376]
- [Request Delegate Generator fails to compile endpoint with Uri? parameter with CS8600 errors][dotnet-aspnetcore-48378]
- [Application using Request Delegate Generator fails to start with KeyNotFoundException][dotnet-aspnetcore-48379]

Again, I think this really highlights the benefits of testing existing codebases with the preview releases to the
product teams. This isn't to say that the code being delivered by the teams is buggy - they work dilligently to ensure
quality - but as with anything, the wider the range of use cases you encounter, the more likely you are to find issues
hiding in the edge cases and real world usage scenarios.

For me the most interesting one was _[Request Delegate Generator code counts as user code for code coverage][dotnet-aspnetcore-48376]_
issue. The ASP.NET Core team care about the coverage of _their own_ code, but the impact of changes on users' own code
isn't neccessarily something that's going to come about from their own internal testing. However, being a former
quality assurance professional, this is the sort of thing I care about deeply for my own codebases. This issue was
also something that was easy to overlook, but also really easy to fix - [so I did][dotnet-aspnetcore-48377]!

That's one of the great things about open source software - anyone can contribute and help make it better. It's
also a great way to side-step things like prioritisation backlogs - if you care deeply about an issue, you can make
fixing it your own priority. Of course the change has to be reviewed and accepted by the team, but _doing_ the initial
work to get the ball rolling is a great way to get things moving.

### A First-Class Time Abstraction

Dealing with the _[flow of time][wibbly-wobbly]_ is one of the most common reasons to need to introduce abstractions
and mocks into a codebase for testing. Properties like `DateTimeOffset.UtcNow` just aren't easily testable, so if you
have code that uses them, you need to introduce abstractions to make it testable. If you have code that behaves differently
based on the date and/or time, maybe something that's sensitive to weekends or time zone changes, then you need a way
to test that whenever you want, rather than waiting for the right time to come around or changing the time on your computer
back and forth.

[Many abstractions for time already exist][dotnet-aspnetcore-16844], such as [Noda Time][noda-time], and you can always
roll your own, but the lack of a first-class time abstraction within the framework itself has always been a bit of a pain
point as it makes code that depends on the time within the framework itself (such as timers) hard(er) to test.

Now .NET provides its own abstraction for time in the form of the [`TimeProvider`][time-provider] abstraction. This
is a great fit for existing applications, particularly for those like my own where I've pulled in the entire of NodaTime
just to provide a comon time abstraction.

With `TimeProvider` I can now remove the need to ship all of the additional dependencies that NodaTime brings with it,
such as time zone data, and instead just use `TimeProvider` in these applications. This makes the dependency graph of my
applications much simpler, and also makes them smaller and faster to start up.

## Preview 5

With the release of [preview 5][preview-5] in June, there were two things that I thought were of interest.

### Dynamic PGO 🤝 Playwright .NET

Something that came up in a few of my applications after updating to preview 5 was that for _some_ of the applications,
their [Playwright][] UI tests were starting to fail with a `NullReferenceException`. This was a bit of a head-scratcher
as it consistently failed in GitHub Actions CI, but not locally in Visual Studio when those tests were run _individually_.
After spending more time that I'd like debugging things and running the tests locally through Playwright .NET's own source
code with some debugging from the `console.log()` school of thought, I was able to determine that _something_ weird was
going on with some asynchronous code inside Playwright. This lead me to open [an issue][dotnet-runtime-87628] in the
.NET runtime repository, thinking that maybe something really weird with threads and tasks had been broken.

A member of the .NET team investigated the issue, and found that actually the triggering factor for the bug was that
[Dynamic Profile Guided Optimization][dynamic-pgo] (PGO) was enabled by default in preview 5 for Release builds. This
made the way the issue didn't always happen make sense. When running tests in Debug mode, PGO isn't enabled. When only
one test is run, then the JIT doesn't have enough time to optimise the code, so the issue doesn't happen either.

This was [raised as an issue with the Playwright team][microsoft-playwright-dotnet-2617], as the code was breaking due
to the way that the stack was being walked to find the names of a caller of a method as part of Playwright's tracing
functionality. When PGO inlined the code, the names were lost, and the code didn't handle that and would then hit null
references that it didn't expect.

My colleague [Stuart Lang][slang25] saw the issue after I'd originally asked him to assure me I wasn't doing something
silly before I opened the GitHub issue. He dug into the issue further and [found a way to fix the issue][microsoft-playwright-dotnet-2620]
by disabling inlining in various places inside the code so that the code that walks the stack to not be broken by Dynamic PGO.

By using the [`1.36.0-beta-1`][playwright-beta] of Playwright .NET, the issue is resolved and now the UI tests work again!

### TimeProvider Testing

Preview 5 also introduced a new [testing package][time-provider-testing] you can use with the `TimeProvider` abstraction
to make it easy to mock the time in your tests. This is a great addition and it means you can more easily set up tests
for code that depends on the time without having to configure the behaviour with a library such as [Moq][moq] yourself.

I've been contributing to the release of [Polly v8][polly-v8] over the last few months, where we've also been working
on consuming the new `TimeProvider` API to make it easier to test Polly itself. As part of this work, I thought I would
try out the new testing package to see whether it would reduce the amount of boilerplate code in our tests to set up
mocks for the clock.

Unfortunately, in adopting it I [found an issue in the runtime itself][dotnet-runtime-88000] where if code that used
an infinite timeout was run with a mocked `TimeProvider`, then an exception would be thrown due to an integer overflow.

I'm hoping to re-adopt the testing package into [Polly's .NET 8 branch][polly-dotnet-8] once this issue is resolved
in a future preview.

## Summary

I hope you've found this run down of our experiences and the issues we found with previews 1-5 of .NET 8 interesting.

As you can see several issues were found during our testing, with them all being raised as issues in the relevant
repositories and at the time of writing all of them either fixed or with a fix in development.

In the next post in this series, we'll take a look at upgrading to .NET 8 Preview 6: _[Part 4 - Preview 6][part-4]_.

## Upgrading to .NET 8 Series Links

You can find links to the other posts in this series below.

- [Part 1 - Why Upgrade?][part-1]
- [Part 2 - Automation is our Friend][part-2]
- Part 3 - Previews 1-5 (this post)
- [Part 4 - Preview 6][part-4]
- [Part 5 - Preview 7 and Release Candidates 1 and 2][part-5]
- [Part 6 - The Stable Release][part-6]

[aot]: https://learn.microsoft.com/aspnet/core/fundamentals/native-aot "ASP.NET Core support for native AOT"
[artifacts-output]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-3/#simplified-output-path "Simplified output path"
[docker-port-change]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-1/#net-container-images ".NET Container images"
[dotnet-aspnetcore-16844]: https://github.com/dotnet/aspnetcore/issues/16844 "Too many ISystemClock definitions"
[dotnet-aspnetcore-46907]: https://github.com/dotnet/aspnetcore/issues/46907 "ASP0019 should not fire if code guards with ContainsKey()"
[dotnet-aspnetcore-46936]: https://github.com/dotnet/aspnetcore/issues/46936 "ASP0023 Ambiguous route warning when using both Http* and Route attributes"
[dotnet-aspnetcore-47202]: https://github.com/dotnet/aspnetcore/issues/47202 "RequestDelegateGenerator throws NullReferenceException in ASP.NET Core 8 preview 2"
[dotnet-aspnetcore-48307]: https://github.com/dotnet/aspnetcore/issues/48307 "RequestDelegateGenerator fails to detect route pattern that uses a constant for its value"
[dotnet-aspnetcore-48376]: https://github.com/dotnet/aspnetcore/issues/48376 "Request Delegate Generator code counts as user code for code coverage"
[dotnet-aspnetcore-48377]: https://github.com/dotnet/aspnetcore/pull/48377 "Add [GeneratedCode] for more RDG output"
[dotnet-aspnetcore-48378]: https://github.com/dotnet/aspnetcore/issues/48378 "Request Delegate Generator fails to compile endpoint with Uri? parameter with CS8600 errors"
[dotnet-aspnetcore-48379]: https://github.com/dotnet/aspnetcore/issues/48379 "Application using Request Delegate Generator fails to start with KeyNotFoundException"
[dotnet-runtime-87628]: https://github.com/dotnet/runtime/issues/87628 "Possible Issue with AsyncLocal<T>/TaskCreationOptions.RunContinuationsAsynchronously"
[dotnet-runtime-88000]: https://github.com/dotnet/runtime/issues/88000 "Cannot create a CancellationTokenSource with an infinite delay with TimeProvider that is not TimeProvider.System"
[dotnet-sdk-31882]: https://github.com/dotnet/sdk/issues/31882 "NuGet package validation fails when UseArtifactsOutput=true"
[dynamic-pgo]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-5/#codegen "Dynamic Profile Guided Optimization"
[eks]: https://aws.amazon.com/eks/ "Amazon Elastic Kubernetes Service"
[microsoft-playwright-dotnet-2617]: https://github.com/microsoft/playwright-dotnet/issues/2617 "Stack inspection done by playwright is fragile and breaks with Dynamic PGO enabled"
[microsoft-playwright-dotnet-2620]: https://github.com/microsoft/playwright-dotnet/pull/2620 "Prevent TieredPGO inlining from breaking stack trace walking logic"
[minimal-apis]: https://learn.microsoft.com/aspnet/core/fundamentals/minimal-apis/overview "Minimal APIs overview"
[moq]: https://github.com/moq/moq#readme "Moq on GitHub"
[noda-time]: https://nodatime.org/ "Noda Time"
[nuget-validation]: https://devblogs.microsoft.com/dotnet/package-validation/ "Package Validation"
[part-1]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-1-why-upgrade "Why Upgrade?"
[part-2]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-2-automation-is-our-friend "Automation is our Friend"
[part-4]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-4-preview-6 "Preview 6"
[part-5]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-5-preview-7-and-rc-1-2 "Preview 7 and Release Candidates 1 and 2"
[part-6]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-6-stable-release "The Stable Release"
[playwright]: https://playwright.dev/dotnet/ "Playwright for .NET"
[playwright-beta]: https://www.nuget.org/packages/Microsoft.Playwright/1.36.0-beta-1 "Microsoft.Playwright 1.36.0-beta-1"
[polly-dotnet-8]: https://github.com/App-vNext/Polly/pull/1144 "Add support for .NET 8"
[polly-v8]: https://github.com/App-vNext/Polly/issues/1048 "Polly v8 - Architectural changes"
[preview-1]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-1/ "Announcing .NET 8 Preview 1"
[preview-2]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-2/ "Announcing .NET 8 Preview 2"
[preview-3]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-3/ "Announcing .NET 8 Preview 3"
[preview-4]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-4/ "Announcing .NET 8 Preview 4"
[preview-5]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-5/ "Announcing .NET 8 Preview 5"
[rdg]: https://devblogs.microsoft.com/dotnet/asp-net-core-updates-in-dotnet-8-preview-3/#minimal-apis-and-native-aot "Minimal APIs and native AOT"
[rootless]: https://devblogs.microsoft.com/dotnet/securing-containers-with-rootless/ "Secure your .NET cloud apps with rootless Linux Containers"
[slang25]: https://github.com/slang25 "Stuart Lang"
[source-generator]: https://learn.microsoft.com/dotnet/csharp/roslyn-sdk/source-generators-overview "Source Generators"
[time-provider]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-4/#introducing-time-abstraction "Introducing Time abstraction"
[time-provider-testing]: https://www.nuget.org/packages/Microsoft.Extensions.TimeProvider.Testing "Microsoft.Extensions.TimeProvider.Testing"
[wibbly-wobbly]: https://www.youtube.com/watch?v=q2nNzNo_Xps "Big ball of wibbly wobbly... time-y wimey... stuff"
