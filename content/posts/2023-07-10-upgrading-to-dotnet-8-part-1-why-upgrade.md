---
title: "Upgrading to .NET 8: Part 1 - Why Upgrade?"
date: 2023-07-10
tags: dotnet,preview,upgrade
layout: post
description: "Why should you upgrade to .NET 8?"
image: "https://cdn.martincostello.com/blog_spiderman-upgrades.jpg"
---

Another year, another new major version of .NET is coming - .NET **8**, to be specific.

I write that like it's brand new information - it's been coming for a while, what with
[.NET 8 Preview 1 having being released in Feburary][dotnet-8-preview-1] - but it's only
recently occured to me to write this blog post series (yes, a series, more on that later).

As annouced a few releases ago, a new major version of .NET is [released every November][dotnet-release-cadence].
These alternate between an odd-numbered Short Term Support (STS) release and an even-numbered
Long Term Support (LTS) release ([see here][dotnet-release-types]).

There's a nice graphic here from the .NET website that illustrates how things look today:

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_dotnet-8-releases.svg" alt="A timeline showing the support for .NET 5 in 2020 through to .NET 9 in 2024" title="A timeline showing the support for .NET 5 in 2020 through to .NET 9 in 2024">

That means .NET 8 will be the next LTS release and supercede .NET 6 _and also_ .NET 7 by the end of 2024.

But why should you upgrade to .NET 8? [Staying supported][dotnet-support-policy] and
patched is the primary reason, but there's another reason that sounds much more compelling:

> _"The first thing that you can do to get free performance in your ASP.NET or .NET applications is to upgrade your .NET version."_
>
> _[Damian Edwards][damian-edwards]_

READMORE

As Damian says [in this talk from Microsoft Build 2023][dotnet-performance-deep-dive], upgrading
.NET will often give you **free** performance improvements in your applications. Just by upgrading
your .NET SDK and your Target Framework Moniker (TFM) to the latest version, you can benefit from
the performance improvements that have been made in the runtime and libraries since the previous
release. You don't even need to adopt any new features or APIs to get the performance improvements - just
leave your code as it is.

The caveat to this is that you might need to fix a few breaking changes or new analyser warnings,
but these are typically few in number and are usually easy to fix.

This screenshot from the talk illustrates the sort of improvements you can expect to see from a simple upgrade.

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_dotnet-8-performance.png" alt="A bar chart comparing the requests per second gained by upgrading from .NET Core 3.1 to .NET 5, 6, 7 and 8 preview 4" title="A bar chart comparing the requests per second gained by upgrading from .NET Core 3.1 to .NET 5, 6, 7 and 8 preview 4">

That makes it seem like a no-brainer to upgrade to me. With lots of people being cost concious these days,
[penny pinching in the cloud][penny-pinching] can be a big deal, and small improvements magnified across
a large fleet of servers can help make for noticeable savings to your annual `$cloud_provider` bills.

But why am I writing this as a blog post series, and why is it starting in July and not November?

## Testing .NET previews

New releases of .NET are typically in active development for months before they are released, usually with
about 10 previews and release candidates available before the final stable _"point zero"_ release. As I've
[written about before][jet-improving-aspnet-core], testing these previews is a great way to help the .NET team.

> _Have you ever wondered why Microsoft releases preview versions of their products before the final release?
> Well, it’s so real customers can help ensure their quality before they go live._

I use .NET _a lot_ - for both for my personal projects as well as for my day job, so I'm always keen to try
out new functionality and help test things ahead of the release of a new major version. As well as giving
back to the wider .NET open source community, it also selfishly helps ensure that my use cases don't get broken too!

At [Just Eat Takeaway.com][jet-careers], we have a .NET [Guild][guilds] of which I'm the chair which promotes
adoption of best practice to our use of .NET internally. As part of the guild, we have a working group that
meets monthly to discuss the latest .NET preview releases and how we can adopt it internally. We regularly test
the latest previews on long-lived branches to try them out with our real-world code bases in their [infinite diversity][idic].

This is the second year we've run the .NET Early Adopters group and it continues to bear fruit. So far with .NET 8 we've
collectively found and reported over a dozen issues to the .NET teams, including a few that we've also fixed ourselves.

Giving timely feedback to the .NET teams helps them to fix issues before the final release, which is a win for everyone.
This gives us a high confidence to adopt the latest .NET releases as soon as they are available, rather than wait
a period of time for them to _"bake in"_ by letting others [step on the rakes][rakes] for us. If everyone did that,
no one would ever upgrade anything!

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_spiderman-upgrades.jpg" alt="Meme of three Spider-Men pointing at each other about upgrading .NET" title="Meme of three Spider-Men pointing at each other about upgrading .NET">

## What's in this series?

As the idea for this blog series is recent, it's already running behind. I'm going to try and catch up with
a post later this week about our experiences with previews 1 through 5, and then after that there will be at
least one post a month to coincide with the release of each new .NET 8 preview until the stable release in November.

Each post will typically contain a summary of our experiences, warts and all, of updating our code bases to
the latest preview that are notable. I won't try and cover every single change, but will try and highlight
interesting issues and deep drive into the use and adoption of some of the new functionality.

I encourage you to also try out the .NET 8 previews and report any issues you find to the .NET teams. You can
find links to download the latest .NET 8 previews from the [.NET website][dotnet-8-downloads].

Read the next part in this series: _[Part 2 - Automation is our Friend][part-2]_.

## Upgrading to .NET 8 Series Links

You can find links to the other posts in this series below.

- Part 1 - Why Upgrade? (this post)
- [Part 2 - Automation is our Friend][part-2]
- [Part 3 - Previews 1-5][part-3]
- [Part 4 - Preview 6][part-4]
- [Part 5 - Preview 7 and Release Candidates 1 and 2][part-5]
- [Part 6 - The Stable Release][part-6]

[damian-edwards]: https://twitter.com/DamianEdwards "@DamianEdwards on Twitter"
[dotnet-8-downloads]: https://dotnet.microsoft.com/download/dotnet/8.0 "Download .NET 8"
[dotnet-performance-deep-dive]: https://build.microsoft.com/en-US/sessions/28588f70-fb54-447a-b778-7ef02c8ffdf8 "Deep dive into .NET performance and native AOT - Microsoft Build"
[dotnet-8-preview-1]: https://devblogs.microsoft.com/dotnet/announcing-dotnet-8-preview-1/ "Announcing .NET 8 Preview 1"
[dotnet-release-cadence]: https://dotnet.microsoft.com/platform/support/policy/dotnet-core#cadence ".NET release cadence"
[dotnet-release-types]: https://dotnet.microsoft.com/platform/support/policy/dotnet-core#release-types ".NET Release types"
[dotnet-support-policy]: https://dotnet.microsoft.com/platform/support/policy/dotnet-core ".NET and .NET Core Support Policy"
[guilds]: https://www.atlassian.com/agile/agile-at-scale/spotify "The Spotify Model for Scaling Agile"
[idic]: https://memory-alpha.fandom.com/wiki/IDIC "Infinite Diversity in Infinite Combinations on Memory Alpha"
[jet-careers]: https://careers.justeattakeaway.com/global/en/c/tech-product-jobs "Tech & Product Careers at Just Eat Takeaway.com"
[jet-improving-aspnet-core]: https://medium.com/justeattakeaway-tech/improving-asp-net-core-before-it-ships-3e44b6f65054 "Improving ASP.NET Core Before It Ships 🚢"
[part-2]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-2-automation-is-our-friend "Automation is our Friend"
[part-3]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-3-previews-1-to-5 "Previews 1-5"
[part-4]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-4-preview-6 "Preview 6"
[part-5]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-5-preview-7-and-rc-1-2 "Preview 7 and Release Candidates 1 and 2"
[part-6]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-6-stable-release "The Stable Release"
[penny-pinching]: https://www.hanselman.com/blog/penny-pinching-in-the-cloud-running-and-managing-lots-of-web-apps-on-a-single-azure-app-service "Penny Pinching in the Cloud: Running and Managing LOTS of Web Apps on a single Azure App Service"
[rakes]: https://youtu.be/2WZLJpMOxS4 "Sideshow Bob stepping on rakes"
