---
title: "Upgrading to .NET 8: Part 4 - Preview 6"
date: 2023-07-19
tags: dotnet,preview,upgrade
layout: post
description: "Highlights from upgrading to .NET 8 preview 6"
cdnImage: "dotnet-bot.png"
---

Following on from [part 3][part-3] of this series, I've been continuing
to upgrade my projects to .NET 8 - this time to preview 6. In this post
I'll cover more experiences with the new source generators with this
preview as well as a new feature of C# 12: _primary constructors_.

<!--more-->

## More Fun with Source Generators

### Request Delegate Generator - Round 3

Preview 6 includes further changes to the _[Request Delegate Generator][rdg]_
since last month's preview 5, so time to dive in again and see how things
are shaping up.

This release included [what I thought was the fix][dotnet-aspnetcore-48377]
to exclude the generated code from the code coverage reports. However, it
turns out that I misunderstood the coverlet code coverage defaults, and
generated code still isn't excluded by default. It's still desirable that the
code that's generated has this attribute, so it's not a wasted effort, but
it means that it still requires me to change my coverlet configuration to
not include the code in my coverage reports.

This is easily fixed with the following addition to my project files where
I use the coverlet MSBuild integration: `<ExcludeByAttribute>GeneratedCodeAttribute</ExcludeByAttribute>`.

Once I updated my configuration, I enabled the Request Delegate Generator in
[all of the repositories I'm testing .NET 8 with][dotnet-8-upgrade-report].
This was mostly successful, but I did find two issues with the generator.

The first was [an issue with][dotnet-aspnetcore-49381] code being generated
that didn't honour nullable reference types correctly for a required struct.
As of writing this blog post this is still being looked into.

[The second issue][dotnet-aspnetcore-49384] was that the generator wasn't
emitting code correctly where the lambda method for the endpoint captures
a type parameter from the method calling the `Map*()` method. For example:

```csharp
public static RouteHandlerBuilder MapRepositoryUpdate<T>(
    this IEndpointRouteBuilder endpoints,
    string pattern,
    Func<IConfigurationRepository, RepositoryId, T, CancellationToken, Task<bool>> operation)
{
    return endpoints.MapPatch(pattern, async (
        long installationId,
        long repositoryId,
        [FromBody] Payload<T> request,
        InstallationService service,
        IConfigurationRepository repository,
        CancellationToken cancellationToken) =>
    {
        if (!await service.UserHasAccessToRepositoryAsync(installationId, repositoryId))
        {
            return Results.NotFound();
        }

        await repository.EnsureRepositoryAsync(repositoryId, cancellationToken);

        return await operation(repository, new(repositoryId), request.Value, cancellationToken) switch
        {
            false => Results.Conflict(),
            true => Results.NoContent(),
        };
    }).RequireAuthorization();
}

internal sealed record Payload<T>(T Value);
```

This turned out to be a [known issue][dotnet-aspnetcore-47338], but unfortunately
it doesn't appear to be in scope to be resolved as part of the .NET 8 release.
Instead in preview 7 the generator will [emit a warning][dotnet-aspnetcore-49417]
for this scenario (and others) that aren't supported as part of this year's release.

Along with this testing, [Safia Abdalla][safia-abdalla] from the ASP.NET Core team
reached out to me and asked if I'd do some testing with the latest nightly builds
of ASP.NET Core with Request Delegate Generator enabled. This is because
[quite a big change][dotnet-aspnetcore-48817] was made for preview 7 to build the
source generators on top of the new C# 12 feature: _[Interceptors][csharp-interceptors]_.

I was happy to help, so I updated a few of my projects to use the latest nightly builds
from the [dotnet/installer][dotnet-installer] repository. I didn't find any issues
with the changes which is a great sign for preview 7 on that front, but I did find
[a new issue][dotnet-runtime-88842] with the Just in Time (JIT) compiler related
to [SIMD12][simd] instructions on Linux x64 that caused my tests to crash. This
issue should be fixed as part of preview 7 too.

### Configuration Binding Source Generator

From one source generator to another, preview 6 also includes various fixes
to the [Configuration Binding Source Generator][configuration-binding-source-generator]
that was introduced in preview 3. I hadn't tried this out yet, so again I
turned this on in all of the repositories I'm testing .NET 8 with. This was
certainly a fruitful excercise in terms of finding bugs!

Across the various repositories I turned the generator on for, I found a
total of four different issues with the code produced by the generator.

- Another variant on the `[GeneratedCode]` attribute missing, impacting code coverage - [dotnet/runtime#89007][dotnet-runtime-89007]
- A compiler error generating code for a struct - [dotnet/runtime#89010][dotnet-runtime-89010]
- The generator failing to generate any code on Linux and macOS - [dotnet/runtime#89014][dotnet-runtime-89014]
- A compiler error generating code for for nullable reference types - [dotnet/runtime#89019][dotnet-runtime-89019]

As a bonus, while the .NET team investigated the issues I found, they also
found a fifth issue where [redundant code was being generated][dotnet-runtime-89043].

I think these issues again highlight how valuable community testing of pre-releases
of .NET can be. Finding these issues earlier allows for more use cases to
be flushed out while features are still under development, leading to the
new features being more stable and having more depth of coverage ahead of
release candidates and the final release being made available. The sooner
issues are identified, the more time the .NET team has to fix them before
shipping! 🚢

## Primary Constructors

_[Primary constructors][primary-constructors]_ are a new feature in C# 12.
In short, they allow you remove the need to define a traditional explicit
constructor to pass parameters to use in a class or struct.  Instead, you
declare the parameters as part of the class/struct declaration, where you
can then capture the parameters to use in the members of the type, such as
to assign the default value of a property.

To use primary constructors, you just need to opt-in to C#12 by enabling
preview language features in the .NET SDK in your project file(s) (or in
`Directory.Build.props`) like this: `<LangVersion>preview</LangVersion>`

This isn't a new feature in preview 6, but this is the first preview I've
tried it out with. To be honest, I wasn't particularly excited about this
feature when it was first announced, but now I've tried it I've been won
over. Suprisingly, the place that really won me over was in some of my
test code!

For example, [to output logs from ASP.NET Core in xunit tests][logging-with-xunit],
you need to pass an instance of `ITestOutputHelper` to the constructor of
your test class. This then lets you redirect the logs from your application under
test to xunit using a package such as [my xunit logging NuGet package][xunit-logging].

In many of my projects, I have a base class for my tests that handles
this for me, but it still needs the derived test classes to also declare
a constructor to pass through the `ITestOutputHelper` instance. This results
in a test class that looks something like this:

```csharp
public class MyTests : TestsBase
{
    public MyTests(ITestOutputHelper outputHelper)
        : base(outputHelper)
    {
    }

    // Here be tests...
}
```

With the adoption of primary constructors, this can be simplified to
the following:

```csharp
public class MyTests(ITestOutputHelper outputHelper) : TestsBase(outputHelper)
{
    // Here be tests...
}
```

Code formatting preferences aside, I think this is a great improvement
as it not just reduces the number of lines of code you need in your types,
but I think it also makes the code a lot neater and has less ceremony.

This is one of those changes where I might however not adopt the change
en masse in existing projects to reduce code churn for peers reviewing
pull requests, but is the sort of change I'd slowly adopt over time as
I'm working on a project asI touch individual files.

Overall I think this is a nice addition to C# as it continues to evolve.

## Summary

In this post we looked at the latest changes to the new source generators
coming as part of the .NET 8 release, as well as looking at a use case for
adopting primary constructors.

In the next post in this series, we'll take a look at upgrading to .NET 8
Preview 7 as well as release candidates 1 and 2: _[Part 5 - Preview 7 and Release Candidates 1 and 2][part-5]_.

## Upgrading to .NET 8 Series Links

You can find links to the other posts in this series below.

- [Part 1 - Why Upgrade?][part-1]
- [Part 2 - Automation is our Friend][part-2]
- [Part 3 - Previews 1-5][part-3]
- Part 4 - Preview 6 (this post)
- [Part 5 - Preview 7 and Release Candidates 1 and 2][part-5]
- [Part 6 - The Stable Release][part-6]

[configuration-binding-source-generator]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-6/#configuration-binding-source-generator-improvements "Configuration binding source generator improvements"
[csharp-interceptors]: https://devblogs.microsoft.com/dotnet/new-csharp-12-preview-features/#interceptors "Interceptors"
[dotnet-8-upgrade-report]: https://gist.github.com/martincostello/2083bcc83f30a5038175e4f31e0fc59f/a8ecc1f7f07f1e51b1ab96966710e8cdbc8cc088 ".NET vNext Upgrade Report on 18/07/2023"
[dotnet-aspnetcore-47338]: https://github.com/dotnet/aspnetcore/issues/47338 "RDG does not support generic types from outer scope"
[dotnet-aspnetcore-48377]: https://github.com/dotnet/aspnetcore/pull/48377 "Add [GeneratedCode] for more RDG output"
[dotnet-aspnetcore-48817]: https://github.com/dotnet/aspnetcore/pull/48817 "Update RDG to use interceptors feature"
[dotnet-aspnetcore-49381]: https://github.com/dotnet/aspnetcore/issues/49381 "Request Delegate Generator fails to compile code with CS8601 warning for required non-nullable record parameters"
[dotnet-aspnetcore-49384]: https://github.com/dotnet/aspnetcore/issues/49384 "Request Delegate Generator fails to compile code with CS0246 error for endpoint with generic type parameter"
[dotnet-aspnetcore-49417]: https://github.com/dotnet/aspnetcore/pull/49417 "Emit diagnostics for unsupported RDG scenarios"
[dotnet-installer]: https://github.com/dotnet/installer "dotnet/installer on GitHub"
[dotnet-runtime-88842]: https://github.com/dotnet/runtime/issues/88842 "AccessViolationException or InvalidOperationException thrown in local method with .NET 8 preview 7 nightly"
[dotnet-runtime-89007]: https://github.com/dotnet/runtime/issues/89007 "CoreBindingHelper for configuration binding source generator should be marked as [GeneratedCode]"
[dotnet-runtime-89010]: https://github.com/dotnet/runtime/issues/89010 "Configuration binding source generator fails with CS8598 error"
[dotnet-runtime-89014]: https://github.com/dotnet/runtime/issues/89014 "Configuration binding source generator throws ArgumentOutOfRangeException on macOS and Linux"
[dotnet-runtime-89019]: https://github.com/dotnet/runtime/issues/89019 "Configuration binding source generator fails to compile with CS8600 when using nullable reference types"
[dotnet-runtime-89043]: https://github.com/dotnet/runtime/issues/89043 "When binding to interface collections, config generator shouldn't generate logic for both interface & mapping collection type."
[logging-with-xunit]: https://blog.martincostello.com/writing-logs-to-xunit-test-output/ "Writing Logs to xunit Test Output"
[part-1]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-1-why-upgrade "Why Upgrade?"
[part-2]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-2-automation-is-our-friend "Automation is our Friend"
[part-3]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-3-previews-1-to-5 "Previews 1-5"
[part-5]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-5-preview-7-and-rc-1-2 "Preview 7 and Release Candidates 1 and 2"
[part-6]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-6-stable-release "The Stable Release"
[primary-constructors]: https://devblogs.microsoft.com/dotnet/check-out-csharp-12-preview/#primary-constructors-for-non-record-classes-and-structs "Primary constructors for non-record classes and structs"
[rdg]: https://devblogs.microsoft.com/dotnet/asp-net-core-updates-in-dotnet-8-preview-3/#minimal-apis-and-native-aot "Minimal APIs and native AOT"
[safia-abdalla]: https://github.com/captainsafia "@captainsafia on GitHub"
[simd]: https://en.wikipedia.org/wiki/Single_instruction,_multiple_data "Single instruction, multiple data"
[xunit-logging]: https://github.com/martincostello/xunit-logging#readme "martincostello/xunit-logging on GitHub"
