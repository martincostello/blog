---
title: "Upgrading to .NET 8: Part 5 - Preview 7 and Release Candidates 1 and 2"
date: 2023-10-13
tags: dotnet,preview,upgrade
layout: bloglayout
description: "Highlights from upgrading to .NET 8 preview 7 and release candidates 1 and 2"
image: "https://cdn.martincostello.com/blog_dotnet-bot.png"
---

This post is a bumper edition, covering three different releases:

- [Preview 7][preview-7]
- [Release Candidate 1][rc-1]
- [Release Candidate 2][rc-2]

I had intended to continue the post-per-preview series originally, but time got away from me
with preview 7, plus there wasn't much to say about it, and then I went on holiday for two weeks
just as release candidate 1 landed. Given release candidate 2 was released just a few days ago,
instead I figured I'd just catch-up with myself and summarise everything in this one blog post instead!

Release Candidate 2 is also the last planned release before the final release of .NET 8 in November
to conincide with [.NET Conf 2023][dotnet-conf], so this is going to be the penultimate post in this series.

READMORE

## Preview 7

From upgrading the various projects I've been testing .NET 8 with, there were no new issues
to report with preview 7 in August. There were, however, a few changes that I needed to make
with the introduction of a number of new warnings and analyzers added to the .NET SDK and some
of the new libraries.

### More CA1849 Warnings

A number of improvements were made to the [CA1849 analyzer][ca1849] which resulted in a number
of new warnings being identified in code where synchronous versions of APIs were still being used
despite asynchronous versions now being being available.

The main one flushed out in this preview for the code I've been testing was this:

<pre class="highlight plaintext">
<code>using var stream = await response.Content.ReadAsStreamAsync(Context.RequestAborted);
using var document = JsonDocument.Parse(stream);
</pre>

In these cases, the fix is trivial:

<pre class="highlight plaintext">
<code>using var stream = await response.Content.ReadAsStreamAsync(Context.RequestAborted);
using var document = await JsonDocument.ParseAsync(stream);
</pre>

### FakeTimeProvider Experimental Warnings

The new `FakeTimeProvider` started to emit a `EXTEXP0004` warning which needed to be suppressed.
These warnings were removed for RC2 by [dotnet/extensions#4455][dotnet-extensions-4455] after the
new libraries being formally API reviewed, but these just seemed to add noise for people so I'm
not sure why they were needed in the first place if the intention was to ship the APIs as stable
for .NET 8 itself.

Typically things like [`[RequiresPreviewFeatures(...)]`][requires-preview-features] are used in
the core libraries for such warnings, and then when a non-stable API is intended to be shipped
in a stable release, such as with the [Generic Math APIs in .NET 6][generic-maths].

If this pattern were replicated for all new APIs added in new .NET releases before they reach release
candidate it would create _a lot_ of noise for early adopters in my opinion. I hope such warning
patterns don't become commonplace across .NET in the future. 😄

### CompositeFormat

As part of the performance improvements in .NET 8, a new type, `CompositeFormat`, has been added
to move the logic for formatting strings with composite formatting out of `string.Format()` every
time it is used for a specific usage to instead be amortised across all usages with one upfront cost
of parsing it. More information about this can be found in [Stephen Toub's epic blog post][compositeformat]
about performance in .NET 8.

The TL;DR for this though is to identify any usages of `string.Format()` that can be improved this way,
a new [CA1863][ca1863] analyzer warning has been added to the .NET SDK.

An example of a Git diff to change some string formatting to use this new pattern is shown below.

<pre class="highlight plaintext">
<code>using System;
using System.Globalization;
+ using System.Text;

public class Greeter
{
+   private static readonly CompositeFormat GreetingFormat = CompositeFormat.Parse("Hello {0}!");
    public void PrintName(string name)
    {
-       Console.WriteLine(string.Format(CultureInfo.InvariantCulture, "Hello {0}!", name));
+       Console.WriteLine(string.Format(CultureInfo.InvariantCulture, GreetingFormat, name));
    }
}
</pre>

## Release Candidate 1

Release candidate 1 was released in September, and it was also the first release of .NET 8 that has
a _Go-Live_ license, meaning it is supported by Microsoft for use in production. Once I got back from
my holiday, I updated all of the production (and "production") applications I'm responsible for to run
on .NET 8 using RC1.

### Configuration Binding Source Generator

There were a number of regressions in changes made to the [new Configuration Binding Source Generator][configuration-binding-source-generator]
which unfortunately meant that it would not produce code that would compile for a number of my ASP.NET Core applications.

This meant that I had to turn it off completely for RC1. The issues below were all fixed for RC2, so I was able to re-enable it then.

- _[Fix source-gen issue with a binding member access expression being on a separate line from the member expression][dotnet-runtime-90851]_
- _[Config generator doesn't match nullability semantics of built-in Bind][dotnet-runtime-90987]_
- _[Config binding gen omits a needed comma in an emitted Configure overload replacement][dotnet-runtime-91258]_

### Daily Build Testing

Following the testing with .NET 8 daily builds I did to check a fix for the new Request Delegate Generator
in preview 6, I looked into updating my [][update-dotnet-sdk] GitHub Action to support updating repositories
based on the output of the [dotnet/installer][dotnet-installer] repository. With the [v2.3.0][update-dotnet-sdk-230]
release of the action, that became supported. With that released I started to set up parallel branches in
some of my repositories to test the daily builds of .NET 8 where I felt they'd use a wide range of capabilities
to give good test coverage.

I might write a more in-depth blog post about this at some point as I'm planning on using this approach
again next year for .NET 9 starting around preview 1 as it's found a number of bugs that got caught before an
official preview release was published.

Such an issue was [dotnet/runtime#9038][dotnet-runtime-90386]. This was an issue where changes to HttpClientFactory
caused a background task to throw an exception when it was disposed. This in turn caused the .NET test process to
"crash", causing all of my CI builds to fail. I was glad this was caught before RC1 as it would have been a big
blocker for me personally as it would have caused a lot of test failures in my repositories that use the `DefaultHttpClientFactory`.

### Portable Runtime Identifiers

In .NET 8 the Runtime Identifiers (RIDs) used by projects targeting .NET 8 have been changed to be shorter/simpler
and more portable ([dotnet/docs#36527][dotnet-docs-36527]). This had the consequence that for applications being
published as a self-contained deployment for their targeted operating system and architecture, the RID being previously
used would now generate a build error.

For example, instead of publishing for `win10-x64` the RID is now `win-x64`.

This was a simple enough change to make, but affected a fair number of my repositories adopting .NET 8 as a result.

### C# 12 by Default

The RC1 release also changed the default language version for C# projects to be C# 12. Previously you needed to
explictly set the `LangVersion` MSBuild property to either `preview` or `12`, but as-of RC1 the value of `latest`
(which is what I use) was updated to point to C# 12.

This change in turn caused a number of new warnings to be emitted by the compiler to suggest that new code patterns
be used where beneficial. A major source of these warnings came from the new [collection expressions][collection-expressions-csharp]
language feature.

For example, instead of writing this: `List<string> names = new() { "Alice", "Bob", "Charlie" };`

You can now write this: `List<string> names = ["Alice", "Bob", "Charlie"];`

Much terser!

From using it in a few places as suggested, I think I quite like the syntax as it is similar to JavaScript/TypeScript
array usage, so it feels quite natural to use to me. It also has the benefit of being able to leverage some
[new compiler smarts][collection-expressions-compiler] to help improve the performance and memory allocation of your applications.

There were however two cases where analysers didn't like particular code constructs for C# 12. These needed to be suppressed,
but hopefully they'll be resolved in future releases of the relevant projects:

- _[SA1010 false positive when list syntax is used in a field initializer][DotNetAnalyzers-StyleCopAnalyzers-3687]_
- _[xUnit1026 false positive with C# 12 collection literals][xunit-xunit-2789]_

### New Analyzers

There were also a few new analyzers added to the .NET SDK in RC1 that raised new warnings.

#### CA1869

The new [CA1869][ca1869] analyzer warns if you are repeatedly creating a new `JsonSerializerOptions` instance in your code.

Doing this can potentially be a huge [pit of failure][jsonserializeroptions] in an application. I made a mistake in a production
application during the .NET 5 timeframe where a misconfiguration of the dependency injection container for an application meant
that a new instance of `JsonSerializerOptions` was being created for every HTTP request, rather than just once at startup. In
that instance the application in question was affected to the tune of a 200% increase in response times for requests!

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_jsonserializeroptions-too-slow.png" alt="A graph showing the performance impact of the bad change" title="A graph showing the performance impact of the bad change">

While in this case the analyzer may not have helped as it was an issue with the dependency injection configuration, it's good to
know that investment has been made to help avoid developers cause the same problem in different scenarios.

#### NETSDK1212

The trim analyzer for AOT has been present in the .NET SDK for a while, but what I didn't realise is that it isn't supported for
use on projects that don't target at least .NET 6. A new analzser warning, NETSDK1212, was added to warn about this. This is easy
to fix if you have a project doing multi-targeting (such as [Polly][polly]) by adding a condition to project files where the
analyzer is enabled, like so:

<pre class="highlight plaintext">
<code>&lt;PropertyGroup Condition="$([MSBuild]::IsTargetFrameworkCompatible('$(TargetFramework)', 'net6.0'))"&gt;
  &lt;EnableAotAnalyzer&gt;true&lt;/EnableAotAnalyzer&gt;
  &lt;EnableSingleFileAnalyzer&gt;true&lt;/EnableSingleFileAnalyzer&gt;
  &lt;EnableTrimAnalyzer&gt;true/EnableTrimAnalyzer&gt;
  &lt;IsTrimmable&gt;true&lt;/IsTrimmable&gt;
  &lt;/PropertyGroup&gt;
</pre>

## Release Candidate 2

Release candidate 2 was released in October just a few days ago, and it's been pretty uneventful (for me at least).

Other than [one issue I wan into][dotnet-runtime-93335] when I re-enabled the Configuration Binding Source Generator for
one project now the other issues I mentioned above had been fixed, I didn't experience any friction with RC2 at all.

Within a few hours of RC2 being released, I had updated all of the repositories I'm responsible for that were running
RC1 to use .NET 8 RC2 instead. 🚀

## Summary

With those updated, it's just a case of waiting for the final release of .NET 8 in November and then merging all of the
other updates to things like libraries I've been waiting for the stable release to merge.

For example, [updating Polly to use the new `TimeProvider` API][App-vNext-Polly-1144] and replace
[the internal copy of the code][timeprovider-copy] used for the v8.0.0 release.

In the next and final post in this series, we'll take a look at completing the upgrades to .NET 8!

## Upgrading to .NET 8 Series Links

You can find links to the other posts in this series here - I'll keep them updated as new posts are published over the course of 2023.

- [Part 1 - Why Upgrade?][part-1]
- [Part 2 - Automation is our Friend][part-2]
- [Part 3 - Previews 1-5][part-3]
- [Part 4 - Preview 6][part-4]
- Part 5 - Preview 7 and Release Candidates 1 and 2 (this post)

[App-vNext-Polly-1144]: https://github.com/App-vNext/Polly/pull/1144
[ca1849]: https://learn.microsoft.com/dotnet/fundamentals/code-analysis/quality-rules/ca1849
[ca1863]: https://learn.microsoft.com/dotnet/fundamentals/code-analysis/quality-rules/ca1863
[ca1869]: https://learn.microsoft.com/dotnet/fundamentals/code-analysis/quality-rules/ca1869
[collection-expressions-csharp]: https://learn.microsoft.com/dotnet/csharp/language-reference/proposals/csharp-12.0/collection-expressions "Collection expressions"
[collection-expressions-compiler]: https://devblogs.microsoft.com/dotnet/performance-improvements-in-net-8/#collection-expressions "Collection expressions"
[compositeformat]: https://devblogs.microsoft.com/dotnet/performance-improvements-in-net-8/#numbers#string-formatting
[configuration-binding-source-generator]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-6/#configuration-binding-source-generator-improvements "Configuration binding source generator improvements"
[dotnet-conf]: https://www.dotnetconf.net/ ".NET Conf 2023"
[dotnet-docs-36527]: https://github.com/dotnet/docs/issues/36527 "[Breaking change]: Projects targeting .NET 8 and higher will by default use a smaller, portable RID graph"
[dotnet-extensions-4455]: https://github.com/dotnet/extensions/pull/4455 "Remove experimental attributes following API reviews"
[dotnet-installer]: https://github.com/dotnet/installer "dotnet/installer repository in GitHub"
[dotnet-runtime-90386]: https://github.com/dotnet/runtime/issues/90386 "ObjectDisposedException intermittently crashes test process from DefaultHttpClientFactory.ExpiryTimer_Tick"
[dotnet-runtime-90851]: https://github.com/dotnet/runtime/issues/90851 "Fix source-gen issue with a binding member access expression being on a separate line from the member expression"
[dotnet-runtime-90987]: https://github.com/dotnet/runtime/issues/90987 "Config generator doesn't match nullability semantics of built-in Bind"
[dotnet-runtime-91258]: https://github.com/dotnet/runtime/issues/91258 "Config binding gen omits a needed comma in an emitted Configure overload replacement"
[dotnet-runtime-93335]: https://github.com/dotnet/runtime/issues/93335 "Configuration Binding source generator throws InvalidOperationException at runtime for type with custom [TypeConverter] use within a dictionary"
[DotNetAnalyzers-StyleCopAnalyzers-3687]: https://github.com/DotNetAnalyzers/StyleCopAnalyzers/issues/3687 "SA1010 false positive when list syntax is used in a field initializer"
[generic-maths]: https://devblogs.microsoft.com/dotnet/preview-features-in-net-6-generic-math/ "Preview Features in .NET 6 – Generic Math"
[jsonserializeroptions]: https://devblogs.microsoft.com/dotnet/performance-improvements-in-net-8/#collection-expressions#json "JSON"
[part-1]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-1-why-upgrade "Why Upgrade?"
[part-2]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-2-automation-is-our-friend "Automation is our Friend"
[part-3]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-3-previews-1-to-5 "Previews 1-5"
[part-4]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-4-preview-6 "Preview 6"
[preview-7]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-7/ "Announcing .NET 8 Preview 7"
[polly]: https://github.com/App-vNext/Polly "App-vNext/Polly on GitHub"
[rc-1]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-rc1/ "Announcing .NET 8 RC1"
[rc-2]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-rc2/ "Announcing .NET 8 RC2"
[requires-preview-features]: https://github.com/dotnet/designs/blob/main/accepted/2021/preview-features/preview-features.md "Preview Features"
[timeprovider-copy]: https://github.com/App-vNext/Polly/blob/e68645da0eb2270d442c7c229c3c801c48b5f1fc/src/Polly.Core/ToBeRemoved/TimeProvider.cs "TimeProvider.cs in Polly"
[update-dotnet-sdk]: https://github.com/martincostello/update-dotnet-sdk "martincostello/update-dotnet-sdk in GitHub"
[update-dotnet-sdk-230]: https://github.com/martincostello/update-dotnet-sdk/releases/tag/v2.3.0 "martincostello/update-dotnet-sdk v2.3.0"
[xunit-xunit-2789]: https://github.com/xunit/xunit/issues/2789 "xUnit1026 false positive with C# 12 collection literals"
