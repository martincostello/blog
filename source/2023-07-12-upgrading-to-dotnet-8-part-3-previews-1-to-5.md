---
title: "Upgrading to .NET 8: Part 3 - Previews 1-5"
date: 2023-07-12
tags: dotnet,preview,upgrade
layout: bloglayout
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

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_artifacts-output.png" alt="The new artifacts output folder" title="The new artifacts output folder">

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

[preview 4][preview-4]

## Preview 5

[preview 5][preview-5]

[Dynamic BGO break][dotnet-runtime-87628]
[Playwright bug][microsoft-playwright-dotnet-2617]
[Broken clock][dotnet-runtime-88000]

## Summary

I hope you've found this run down of our experiences and the issues we found with previews 1-5 of .NET 8 interesting.

In the next post in this series, we'll take a look at .NET 8 Preview 6.

## Upgrading to .NET 8 Series Links

You can find links to the other posts in this series here - I'll keep them updated as new posts are published over the course of 2023.

- [Part 1 - Why Upgrade?][part-1]
- [Part 2 - Automation is our Friend][part-2]
- Part 3 - Previews 1-5 (this post)

<!--
- [Part 4 - Preview 6][part-4]
-->

[aot]: https://learn.microsoft.com/aspnet/core/fundamentals/native-aot "ASP.NET Core support for native AOT"
[artifacts-output]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-3/#simplified-output-path "Simplified output path"
[docker-port-change]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-1/#net-container-images ".NET Container images"
[dotnet-aspnetcore-46907]: https://github.com/dotnet/aspnetcore/issues/46907 "ASP0019 should not fire if code guards with ContainsKey()"
[dotnet-aspnetcore-46936]: https://github.com/dotnet/aspnetcore/issues/46936 "ASP0023 Ambiguous route warning when using both Http* and Route attributes"
[dotnet-aspnetcore-47202]: https://github.com/dotnet/aspnetcore/issues/47202 "RequestDelegateGenerator throws NullReferenceException in ASP.NET Core 8 preview 2"
[dotnet-runtime-87628]: https://github.com/dotnet/runtime/issues/87628 "Possible Issue with AsyncLocal<T>/TaskCreationOptions.RunContinuationsAsynchronously"
[dotnet-runtime-88000]: https://github.com/dotnet/runtime/issues/88000 "Cannot create a CancellationTokenSource with an infinite delay with TimeProvider that is not TimeProvider.System"
[dotnet-sdk-31882]: https://github.com/dotnet/sdk/issues/31882 "NuGet package validation fails when UseArtifactsOutput=true"
[eks]: https://aws.amazon.com/eks/ "Amazon Elastic Kubernetes Service"
[microsoft-playwright-dotnet-2617]: https://github.com/microsoft/playwright-dotnet/issues/2617 "Stack inspection done by playwright is fragile and breaks with Dynamic PGO enabled"
[minimal-apis]: https://learn.microsoft.com/aspnet/core/fundamentals/minimal-apis/overview "Minimal APIs overview"
[nuget-validation]: https://devblogs.microsoft.com/dotnet/package-validation/ "Package Validation"
[part-1]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-1-why-upgrade "Why Upgrade?"
[part-2]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-2-automation-is-our-friend "Automation is our Friend"

<!--
[part-4]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-4-preview-6 "Preview 6"
-->

[preview-1]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-1/ "Announcing .NET 8 Preview 1"
[preview-2]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-2/ "Announcing .NET 8 Preview 2"
[preview-3]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-3/ "Announcing .NET 8 Preview 3"
[preview-4]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-4/ "Announcing .NET 8 Preview 4"
[preview-5]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-5/ "Announcing .NET 8 Preview 5"
[rdg]: https://devblogs.microsoft.com/dotnet/asp-net-core-updates-in-dotnet-8-preview-3/#minimal-apis-and-native-aot "Minimal APIs and native AOT"
[rootless]: https://devblogs.microsoft.com/dotnet/securing-containers-with-rootless/ "Secure your .NET cloud apps with rootless Linux Containers"
[source-generator]: https://learn.microsoft.com/dotnet/csharp/roslyn-sdk/source-generators-overview "Source Generators"
