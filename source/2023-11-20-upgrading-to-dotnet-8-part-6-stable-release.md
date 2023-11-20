---
title: "Upgrading to .NET 8: Part 6 - The Stable Release"
date: 2023-11-20
tags: dotnet,preview,upgrade
layout: bloglayout
description: "Highlights from upgrading to the stable release of .NET 8"
image: "https://cdn.martincostello.com/blog_dotnet-bot.png"
---

Last week at [.NET Conf 2023][dotnet-conf], the stable release of .NET 8 was released as the latest [Long Term Support][dotnet-support-policy] (LTS) release of the .NET platform.

With the release of .NET 8.0.0 and the end of the preview releases, my past week can be summed up by the following image:

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_upgrade-all-the-things.jpg" alt="The All the Things meme, with the text: Upgrade All The Things To .NET 8" title="The All the Things meme, with the text: Upgrade All The Things To .NET 8">

READMORE

## Upgrading All The Things

You may think that's an exaggeration, but it's not far from the truth. 😅

Over the past 7 days I (or [my automation][part-2]) have upgraded the default branch of over 60 GitHub repositories to use the stable release .NET 8, from either .NET 6/7 or .NET 8 Release Candidate 2.

Highlights of the upgrade have included:

- Updating all the apps I run in Azure App Service and AWS Lambda to run on .NET 8, such as my Alexa Skill [London Travel][london-travel].
- [Polly v8.2.0][polly-820], which adds support for `net8.0` as well as use of the new [`TimeProvider`][timeprovider] API.
- Releases of the aspnet-contrib [OAuth][aspnet-contrib-oauth] and [OpenID][aspnet-contrib-openid] packages for ASP.NET Core 8.

## An (Almost) Pain Free Upgrade

Through testing with the preview releases over this release cycle, the upgrade to the stable release was pretty much a non-event, everything Just Worked™️.

There was only one thing which caught me out, and that was that the `Microsoft.Extensions.Http.Telemetry` NuGet package was renamed between RC2 and the stable release to [`Microsoft.Extensions.Http.Diagnostics`][ms-extensions-http-diag]. This was temporarily confusing as the [annoucement blog post][dotnet-8-annoucement] included the old name, but the 8.0.0 NuGet package was not available. I [queried this with the team][dotnet-extensions-4723] and they confirmed the name change the next day.

Within 24 hours of the release, I'd updated all of the applications and libraries I'm responsible for maintaining to either target .NET 8, or build with the .NET 8 SDK. 🚀

There was however a _tangential_ issue I stumbled into while working through all the updates for both my open source repositories and projects I help maintain internally at work.

## Amazon Linux 2023

On the 9th of November, AWS [announced support for Amazon Linux 2023][aws-lambda-al2023] as a custom runtime for AWS Lambda. I've been waiting for this to be released for a while as it has been in preview for quite some time, and I've been using the Amazon Linux 2 custom runtime since it was released. Amazon Linux 2023 also looks like it'll be the basis for any forthcoming AWS Lambda managed runtime support for .NET 8 (soon I hope 🤞) - it's already used as the basis for the new [Node.js 20.x and Java 21 managed runtimes][aws-lambda-runtimes].

I was just leaving for a short break in Norway when the release was announced, so I didn't see the annoucement until I next sat in front of a computer again on the 14th of November. As I was already (or about to) update a bunch of applications for .NET 8 later in the day, I figured I'd get ahead of the curve and update the AWS Lambda functions I maintain to use the new runtime in advance of those changes.

Things were all pretty painless as with the subsequent .NET 8 updates...or so I thought.

A few days later I noticed that some non-critical automation we use internally to deploy GitHub releases had stopped auto-approving releases deemed as "safe" for automated deployment. I dug into the error logs for the application in Kibana and found the following warning logged:

`The value Europe/London could not be parsed as a TimeZoneInfo value.`

A little refactoring later and adjusting the logging lead to the following exception that was being swallowed on the assumption it would only fail for mis-configured time zone IDs:

<pre class="highlight plaintext"><code>System.TimeZoneNotFoundException: The time zone ID 'Europe/London' was not found on the local computer.
 ---> System.IO.DirectoryNotFoundException: Could not find a part of the path '/usr/share/zoneinfo/Europe/London'.
   at Interop.ThrowExceptionForIoErrno(ErrorInfo errorInfo, String path, Boolean isDirError)
   at Microsoft.Win32.SafeHandles.SafeFileHandle.Open(String path, OpenFlags flags, Int32 mode, Boolean failForSymlink, Boolean& wasSymlink, Func`4 createOpenException)
   at Microsoft.Win32.SafeHandles.SafeFileHandle.Open(String fullPath, FileMode mode, FileAccess access, FileShare share, FileOptions options, Int64 preallocationSize, UnixFileMode openPermissions, Int64& fileLength, UnixFileMode& filePermissions, Boolean failForSymlink, Boolean& wasSymlink, Func`4 createOpenException)
   at System.IO.Strategies.OSFileStreamStrategy..ctor(String path, FileMode mode, FileAccess access, FileShare share, FileOptions options, Int64 preallocationSize, Nullable`1 unixCreateMode)
   at System.TimeZoneInfo.ReadAllBytesFromSeekableNonZeroSizeFile(String path, Int32 maxFileSize)
   at System.TimeZoneInfo.TryGetTimeZoneFromLocalMachineCore(String id, TimeZoneInfo& value, Exception& e)</code>
</pre>

The failures were coming from the calls to [`TimeZoneInfo.FindSystemTimeZoneById(string)`][findtimezonebyid] used to convert UTC `DateTimeOffset` values to their local time zone equivalents. The failure was causing release window definitions used by the automation to be failed to be parsed, so the code was failing safe and not auto-approving the deployments as it didn't know whether it was a safe time of day to do so.

A little more probing around on the Amazon Linux 2023 instead lead me to the conclusion that the [`tzdb`][tzdb] package isn't installed on Amazon Linux 2023. That's an unfortunate change compared to Amazon Linux 2, where it is installed by default. I couldn't find any documentation for Amazon Linux 2023 either way on whether it should or shouldn't have been installed, so I [raised an issue with the AWS .NET team][aws-aws-lambda-dotnet-1620].

It turns out that this is a known issue the AWS Lambda team are aware of, but there's currently no guidance on what to do instead or whether the distribution will be changed to include the tzdb package by default.

I can see the rationale for removing it to make the distribution smaller, but time zone handling is quite a common requirement for applications, so it's a shame it's not included by default as it breaks the ability to use the out-of-the-box .NET APIs for time zone handling (especially with the new [`TimeProvider`][timeprovider] API).

In the meantime, I've worked around the issue by (re)adding a dependency on the [NodaTime][nodatime] NuGet package to handle time zone conversions.

NodaTime contains an embedded version of the tzdb database, which can be easily used to convert between time zones. I would prefer to not use NodaTime to keep the deployed application size down and not have an extra NuGet dependency to keep up to date, but it's a small change to make.

The code changes required for this scenario are pretty simple, as the following example shows.

Before the code was essentially this:

<pre class="highlight plaintext"><code>var timeZoneId = "Europe/London";
var utcNow = TimeProvider.System.GetUtcNow();
var timeZone = TimeZoneInfo.FindSystemTimeZoneById(timeZoneId);
var localNow = TimeZoneInfo.ConvertTimeFromUtc(utcNow, timeZone);</code>
</pre>

Using NodaTime, the code becomes:

<pre class="highlight plaintext"><code>var timeZoneId = "Europe/London";
var utcNow = TimeProvider.System.GetUtcNow();
var timeZone = DateTimeZoneProviders.Tzdb[timeZoneId];
var localNow = Instant.FromDateTimeUtc(utcNow).InZone(timeZone).ToDateTimeUnspecified();</code>
</pre>

I hope that the AWS teams will be able to add tzdb to Amazon Linux 2023 so I can remove NodaTime again, but if it's not added to the custom
runtime I certainly hope it's added to any forthcoming managed runtime for .NET 8. Otherwise, I can see a lot of developers needing to update
existing code to change how they deal with time zones when running in AWS Lambda compared to using the .NET 6 managed runtime for what should
otherwise be a relatively straightforward upgrade.

## Summary

That's a wrap for .NET 8 for 2023 - now it becomes _"just the current version"_ for background patching and security updates.

As well as replacing both .NET 6 and 7 as the latest and greatest release with its status of LTS, .NET 8 brought us tonnes of improvements;

- Improved performance;
- C# 12;
- Expanded native AoT support;
- [.NET Aspire][aspire];
- and much more!

We truly are spoiled by the .NET team as they continue to invest and iterate on making it a highly competitive and productive development platform.

See you in 2024 for the .NET 9 upgrade! 😎

## Upgrading to .NET 8 Series Links

You can find links to the other posts in this series below.

- [Part 1 - Why Upgrade?][part-1]
- [Part 2 - Automation is our Friend][part-2]
- [Part 3 - Previews 1-5][part-3]
- [Part 4 - Preview 6][part-4]
- [Part 5 - Preview 7 and Release Candidates 1 and 2][part-5]
- Part 6 - The Stable Release (this post)

[aspire]: https://learn.microsoft.com/dotnet/aspire/get-started/aspire-overview ".NET Aspire overview"
[aspnet-contrib-oauth]: https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/releases/tag/8.0.0 "AspNet.Security.OAuth.Providers 8.0.0"
[aspnet-contrib-openid]: https://github.com/aspnet-contrib/AspNet.Security.OpenId.Providers/releases/tag/8.0.0 "AspNet.Security.OpenId.Providers 8.0.0"
[aws-aws-lambda-dotnet-1620]: https://github.com/aws/aws-lambda-dotnet/issues/1620 "Install tzdb on provided.al2023 runtime so that TimeZoneInfo.FindSystemTimeZoneById() works"
[aws-lambda-al2023]: https://aws.amazon.com/about-aws/whats-new/2023/11/aws-lambda-amazon-linux-2023/ "AWS Lambda adds support for Amazon Linux 2023"
[aws-lambda-runtimes]: https://docs.aws.amazon.com/lambda/latest/dg/lambda-runtimes.html
[dotnet-8-annoucement]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8/ "Announcing .NET 8"
[dotnet-conf]: https://www.dotnetconf.net/ ".NET Conf 2023"
[dotnet-extensions-4723]: https://github.com/dotnet/extensions/issues/4723 "Missing stable package for Microsoft.Extensions.Http.Telemetry"
[dotnet-support-policy]: https://dotnet.microsoft.com/platform/support/policy/dotnet-core ".NET and .NET Core Support Policy"
[findtimezonebyid]: https://learn.microsoft.com/dotnet/api/system.timezoneinfo.findsystemtimezonebyid "TimeZoneInfo.FindSystemTimeZoneById(String) Method"
[london-travel]: https://www.amazon.co.uk/dp/B01NB0T86R "London Travel Alexa Skill on amazon.co.uk"
[ms-extensions-http-diag]: https://www.nuget.org/packages/Microsoft.Extensions.Http.Diagnostics
[nodatime]: https://github.com/nodatime/nodatime "NodaTime repository on GitHub.com"
[part-1]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-1-why-upgrade "Why Upgrade?"
[part-2]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-2-automation-is-our-friend "Automation is our Friend"
[part-3]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-3-previews-1-to-5 "Previews 1-5"
[part-4]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-4-preview-6 "Preview 6"
[part-5]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-5-preview-7-and-rc-1-2 "Preview 7 and Release Candidates 1 and 2"
[polly-820]: https://github.com/App-vNext/Polly/releases/tag/8.2.0 "Polly 8.2.0"
[timeprovider]: https://learn.microsoft.com/dotnet/api/system.timeprovider "The System.TimeProvider Class"
[tzdb]: https://www.iana.org/time-zones "IANA Time Zone Database"
