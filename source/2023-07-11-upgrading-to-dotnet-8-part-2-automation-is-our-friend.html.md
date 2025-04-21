---
title: "Upgrading to .NET 8: Part 2 - Automation is our Friend"
date: 2023-07-11
tags: dotnet,preview,upgrade,automation,github,actions
layout: bloglayout
description: "Making it easier to test .NET previews using GitHub Actions for on-going automation."
image: "https://cdn.martincostello.com/blog_dotnet-8-upgrade-report.png"
---

In [part 1 of this series][part-1] I recommended that you prepare to upgrade to .NET 8 and suggested
that you start off by testing the preview releases. Testing the preview releases is a great
way to get a head start on the upgrade process and to identify any issues sooner rather than
later, but it does require an investment of your time from preview to preview each month.

Even if you don't want to test new functionality, you still need to download the new .NET SDK,
update all the .NET SDK and NuGet package versions in your projects, and then test that everything
still works (that's already automated at least, right?). This can be a time-consuming process over
the course of a new .NET release, and it starts to become harder to scale if you want to test lots
of different codebases with the latest preview of the next .NET release.

What if we could automate some of this process so that we only need to focus on the parts
where we as humans really add value compared to the mechanical parts of an upgrade?

In part 2 of this series I'm going to explain how I've gone about automating the boring
parts of the process of testing the latest .NET preview releases using [GitHub Actions][github-actions].

READMORE

## Getting started

The automation is intended to help with moving from preview to preview each month, but the first
preview you wish to test compared to the stable release of .NET 6 (or 7) you currently use will
need to be done manually.

This process can be relatively painless in most cases, requiring you to:

1. Update your .NET SDK version in `global.json` to the latest preview release (e.g. `8.0.100-preview.5.23303.2`).
2. Update your Target Framework(s) to the latest version (e.g. `net6.0` to `net8.0`).
3. Update your NuGet package reference(s) to the latest preview versions (e.g. `8.0.0-preview.5.23280.8`).
4. Fixing (or suppressing - no judgement) any new .NET analyser errors flagged by the .NET SDK.
5. Fixing any breaking changes.
6. Ensuring all your tests still pass.

With these steps in place, you can then commit the changes to a new branch and push it to GitHub.
To make things easy to automate by convention, I do things like this:

- The branch is named `dotnet-vnext` - this means we can reuse the process with minimal changes for .NET 9 in 2024 (and beyond).
- The pull request is left in a draft state - this means it can't be merged until we're ready to do so (such as when the `8.0.100` is released).
- Don't use a fork - it's much easier to manage merge conflicts over the branch's lifetime if you use a branch in the same repository.
- Add `dotnet-vnext` as a triggering branch (e.g. for pull requests) for any GitHub Actions workflows that already run for `main`.

With the one-off preparation to create the branch done, we're now ready to layer on the automation
and [save ourselves time in the long run][is-it-worth-the-time].

## Updating the .NET SDK and NuGet package versions

In May I did a talk at [DDD South West][dddsw] about how you can use GitHub Actions to automate the
process of updating your .NET projects to the latest patch version every month using my
[update-dotnet-sdk][update-dotnet-sdk] GitHub Action and a GitHub Actions reusable workflow.

There's much more detail about how that works [in the sample repository][dotnet-patch-automation],
but in a nutshell it uses the [.NET release notes JSON][dotnet-release-notes] in GitHub to determine
if there's a new .NET release available for a channel (.NET 6, .NET 7 etc.) and then raises a pull
request to update the `global.json` file in the repository to use the latest SDK version. It can also
optionally update your .NET NuGet packages to the latest patch version in the same pull request too.
For one of my repositories that uses SignalR [I also extended it include the SignalR npm package][npm-updates].

It occured to me that I could use the same process to update the version of the .NET SDK for the
current .NET preview release on a branch in the same way. By manually running the workflow to update
the .NET SDK on my `dotnet-vnext` branch instead of `main` I could then have the workflow raise a pull
request to automatically update the .NET SDK version to the latest preview release each month.

To prevent any issues from breaking the long-lived draft pull request for the .NET 8 upgrade itself,
I set up a branch protection rule for the `dotnet-vnext` branch with the following settings:

- Require a pull request before merging - this helps keep the branch stable from automation breakages
- Require status checks to pass before merging (the same ones I use for `main`) - this ensures the CI passes before an update is merged
- Allow force pushes (more on this later)
- Allow bypassing the above settings - this allows a human to bypass the settings that are there to keep the automation in check

Optionally (but recommended), you can then add further automation to handle reviewing and merging
these pull requests to the `dotnet-vnext` branch automatically if the CI passes. For my own repositories
I handle this using my own imaginatively named GitHub automation bot: [costellobot][costellobot] (hey,
[naming is hard][permanence]). There's another solution demonstrated in the [sample repository][dotnet-patch-automation-approvals].

With this all set up, I can now manually run the workflow to update the .NET SDK version on the `dotnet-vnext`
branch when there's a preview version available. This is typically on _[Patch Tuesday][patch-tuesday]_
each month, but not always.

If the upgrade from one preview to the next goes without a hitch, then it automatically merges and there's
nothing that needs doing manually. I'm then free to play with any new features in the latest preview release
separately.

If the CI fails, then I can investigate and fix/report the issue(s) before merging the pull request. It also
gives you a Git commit that's easy to share in a GitHub issue (assuming that your repository is public) to
make it easier for the .NET teams to triage and fix any issues you may find.

This approach makes it easy to focus your time on the parts that are important, rather than having to review _everything_. 😮‍💨

## Rebasing the branches

A downside of a long-lived branch is that development work in the default branch of your repository likely
doesn't stop and things continue to move along.

This means that the `dotnet-vnext` branch can quickly start to accumulate merge conflicts as code is changed
in the default branch. This is particularly true for versions of NuGet packages and the .NET SDK, especially
if you use [Dependabot][dependabot].

While we can easily resolve these conflicts manually, it's quite tedious to do so, and it often just requires
you to manually pick the highest version of the dependency in question when there's a conflict on a line for
a `<PackageReference>`.

This sounds like yet another thing we can automate the process for, right?

For this I created a [GitHub Actions workflow][rebase-workflow] that runs on the `dotnet-vnext` branch and uses
the GitHub API and a custom command-line tool I wrote called _[Rebaser][rebaser]_ ([namesake][debaser]) to automatically
rebase the branch and force-push the changes back to the branch (this is why we allowed force pushes on the
branch when we set up the branch protection rules).

This workflow uses the GitHub API to [find the pull request associated with the `dotnet-vnext` branch][github-api-list-pull-requests-for-commit]
and checks the value of the `mergeable_state` property of the response. If the value is `dirty`, then the branch
has conflicts and needs to be rebased. We ignore other merge states as they don't need us to do anything most of the time.

If the merge state of the branch is found to be _"dirty"_, then Rebaser is run against the repository to rebase
the branch. If any simple conflicts caused by version numbers are found, then it will attempt to resolve them
itself by always chosing the higher version number for the dependency in question. If Rebaser cannot automatically
resolve the conflict, then the rebase is aborted and the workflow emits a warning for a human to resolve the
conflict manually.

In the case where a manual conflict needs to be resolved, Rebaser can be run locally with the `--interactive` flag
specified. This opens Visual Studio Code for each file with a confict in turn and lets the user then leverage
the built-in merge conflict resolution UI to resolve the conflict(s) and then save the file to continue the rebase.
This is really useful in the case where there's lots of easily resolvable conflicts, but a there's just a single
change that needs some manual intervention. In these cases, Rebaser can do the heavy lifting for most of the changes
(the boring ones), and us humans can just focus on the few that need manual attention.

## Stiching things together

At this point we have a workflow that does our version updates for us and another that rebases the branch when it
needs it, but both of these workflows need to be run manually. That's not very automated, is it?

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_an-automated-solution.gif" alt="A drinking bird pressing a button on a keyboard" title="A drinking bird pressing a button on a keyboard">

What if we could automate the automation (whoa, meta) to run the workflows for us when we need them to? What if we
could run the workflows for _all_ of the repositories we're testing .NET previews with? That sounds like something
that would really save us some time each month.

### Checking for new releases

- [A workflow][dotnet-release] checks the [dotnet/core][dotnet-release-notes] repository for changes to the `release-notes/**/releases.json` file(s).
- When changes are found, which _usually_ implies a new release is available, the workflow [raises a repository dispatch event][github-api-create-repo-dispatch] named `dotnet_release`.
- This event triggers the [`update-dotnet-sdks`][update-dotnet-sdks] workflow which runs the `update-dotnet-sdk` workflow in each repository that has opted-in to the automation for the `main` branch. This isn't run for `dotnet-vnext` at the moment as when the release notes change there's likely a new version of .NET 6, 7 and 8 at the same time. Running just for `main` means we can get the .NET 6/7 updates applied to it first, and then the .NET 8 updates later as it would just create a merge conflict anyway.

<s>In the future I might extend this to be smarter and determine whether a preview (or not) has been released, and then run the workflow for either `main` or `dotnet-vnext` as appropriate.</s>

I updated the workflow above to handle this scenario. It now checks the `release-notes/**/releases.json` file(s) for the latest version(s) of .NET that have been released. If any version is a preview, then it will run the workflow for `dotnet-vnext`; for any others it will run the workflow for `main`. The commit that made those changes can be [found here][improvement-commit].

### Checking for conflicts

- [Costellobot][costellobot] receives [webhook payloads for pushes][github-webhook-push] to the repositories it is installed in.
- If a change has been found to have been made in the default branch [to a file that could cause a conflict][dependency-file-changed] in the `dotnet-vnext` branch, then the bot will [raise a repository dispatch event][github-api-create-repo-dispatch] named `dotnet_dependencies_updated`.
- This event triggers the [`rebase`][rebase-workflow] workflow for just that repository, which will then rebase the branch if needed as described above.

A sequence diagram of how all the workflows and events fit together is shown below.

<a href="https://github.com/martincostello/github-automation/blob/main/docs/dotnet-vnext.md#sequence-diagram" target="_blank">
  <img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_dotnet-vnext-sequence.png" alt="A sequence diagram showing the automated workflow" title="A sequence diagram showing the automated workflow">
</a>

More detailed information about how the workflows above operate can be found in _[Testing .NET vNext][dotnet-vnext]_.

## What's the status of the upgrade?

With all this in place, it would be good to know the status of everything in one place. Information that would be good to show includes:

- What version of the .NET SDK is being used?
- Is the `dotnet-vnext` branch using the latest preview version?
- Is the pull request's CI passing?
- Are there any merge conflicts?

GitHub Actions once again comes to the rescue here, as we can use it to create a workflow that uses
the [GitHub CLI][github-cli] to easily query the state of the pull requests in our branches and then
generate a Markdown report using _[Step Summaries][github-actions-step-summaries]_. This shows us a table
of all of the repositories we're testing .NET 8 with and uses [shields.io][shields-io] to generate a
badge that we can use to tell at-a-glance if there's something not right with any repository.

With some inline PowerShell script and the GitHub CLI (`gh`), the [dotnet-upgrade-report workflow][upgrade-report-workflow]
generates a report that provides us with the information we need, using colour coding to draw attention to
any rows that might be of interest. An example of the report can be found [here][upgrade-report-sample].

Here's a snippet from one as an example:

<img class="img-fluid mx-auto d-block w-75" src="https://cdn.martincostello.com/blog_dotnet-8-upgrade-report.png" alt="An example upgrade report showing the status of 9 repositories' upgrades" title="An example upgrade report showing the status of 9 repositories' upgrades">

As you can see above, at the time the report was generated all of the repositories' CI were passing and
using the latest .NET 8 preview SDK, but the [adventofcode repository][advent-of-code] had a merge
conflict that needed resolving.

The report can be generated on-demand by running the workflow manually, but is is also scheduled to run
at 10:00 on working days.

This report allows us to easily track the status of our testing over the course of the preview releases
as well as providing us with an index that allows us to pivot to our pull requests.

## Humans are still needed

With long-lived branches like `dotnet-vnext` being around potentally for months accumulating changes, it's
possible that over time the amount of change in the pull request will snowball and become a larger proposition
to review and merge when the time comes. Developers typically aren't fans of large pull requests with hundreds
of changed lines!

Yet not all of these changes need to wait until .NET 8 is released - some of these changes may be safe to merge
into your `main` branch now.

One example of this is the new [CA1859][ca1859] code analysis rule. This rule suggests that concrete types are
used where possible to improve performance. For example, instead of using `IList<T>` as the type for a private
field in a class you should use `List<T>`. This is because the Just-In-Time compiler (JIT) might be able to
_["devirtualize"][devirtualization]_ the calls to the methods on the concrete type, which can improve performance.

In the case of CA1859, resolving these analysis warnings doesn't rely on the use of the .NET 8 SDK nor any new
APIs. This means that you can safely apply these changes to your default branch now with .NET 6 or 7. The "only"
thing .NET 8 did was bring the _capability to detect_ these issues to us. Not waiting for these changes to be made
until our .NET 8 upgrade is merged means that we can not only benefit from the performance improvements now, but
we also reduce the size of the Git diff in our pull request, making it easier to review and merge when the time comes.
The only downside is that someone could change the code back in the default branch and regress the behaviour,
but I think that's a small issue compared to the benefits of making such changes now.

## Summary

Using the workflows described above in my [github-automation][github-automation] repository, I've been able to automate
a lot of the boring parts of upgrading from one .NET preview to the next. These workflows have allowed me to:

- Watch for new .NET versions using the JSON release notes
- Use the [update-dotnet-sdk] GitHub action to update the .NET SDK and NuGet packages for the repositories we're testing
- Keep changes that break the CI in a separate branch and pull request for manual inspection <s>if</s> when an issue is found
- Automatically rebase the `dotnet-vnext` branch as and when needed (or make it easier to do so manually)

With these parts automated, we can focus on the more interesting parts of the process that come up, like any issues we
might find, or trying out new functionality in the latest preview releases. Nice. 😎

I hope you find the automation I've described above useful and an inspiration for automating .NET version upgrades
for your own application repositories.

In part 3 of this series, we'll look at some of the changes that have been introduced in the first five preview
releases of .NET 8 and some of the interesting issues testing them uncovered: _[Part 3 - Previews 1-5][part-3]_.

## Upgrading to .NET 8 Series Links

You can find links to the other posts in this series below.

- [Part 1 - Why Upgrade?][part-1]
- Part 2 - Automation is our Friend (this post)
- [Part 3 - Previews 1-5][part-3]
- [Part 4 - Preview 6][part-4]
- [Part 5 - Preview 7 and Release Candidates 1 and 2][part-5]
- [Part 6 - The Stable Release][part-6]

[advent-of-code]: https://github.com/martincostello/adventofcode "martincostello/adventofcode on GitHub"
[ca1859]: https://learn.microsoft.com/en-gb/dotnet/fundamentals/code-analysis/quality-rules/ca1859 "CA1859: Use concrete types when possible for improved performance"
[costellobot]: https://github.com/martincostello/costellobot "costellobot on GitHub"
[dddsw]: https://dddsouthwest.com/ "DDD South West"
[debaser]: https://www.youtube.com/watch?v=PVyS9JwtFoQ "Rebaser by The Pixies on YouTube"
[dependabot]: https://docs.github.com/code-security/dependabot/dependabot-version-updates/about-dependabot-version-updates "Dependabot"
[dependency-file-changed]: https://github.com/martincostello/costellobot/blob/8a4352c5938f1c373ea8f64194bc352b5c243ed4/src/Costellobot/Handlers/PushHandler.cs#L60-L82 "DependencyFileChanged() method"
[devirtualization]: https://devblogs.microsoft.com/dotnet/performance-improvements-in-net-6/#jit "Performance Improvements in .NET 6"
[dotnet-patch-automation]: https://github.com/martincostello/dotnet-patch-automation-sample#readme ".NET Patch Automation Sample"
[dotnet-patch-automation-approvals]: https://github.com/martincostello/dotnet-patch-automation-sample#approving-and-merging-pull-requests "Approving and Merging Pull Requests"
[dotnet-release]: https://github.com/martincostello/github-automation/blob/main/.github/workflows/dotnet-release.yml "dotnet-release workflow on GitHub"
[dotnet-release-notes]: https://github.com/dotnet/core/tree/main/release-notes ".NET release notes JSON on GitHub"
[dotnet-vnext]: https://github.com/martincostello/github-automation/blob/main/docs/dotnet-vnext.md "Testing .NET vNext"
[github-actions]: https://github.com/features/actions "GitHub Actions"
[github-actions-step-summaries]: https://github.blog/2022-05-09-supercharging-github-actions-with-job-summaries/ "Supercharging GitHub Actions with Job Summaries on the GitHub Blog"
[github-api-list-pull-requests-for-commit]: https://docs.github.com/rest/commits/commits?apiVersion=2022-11-28#list-pull-requests-associated-with-a-commit "List pull requests associated with a commit"
[github-api-create-repo-dispatch]: https://docs.github.com/rest/repos/repos?apiVersion=2022-11-28#create-a-repository-dispatch-event "Create a repository dispatch event"
[github-automation]: https://github.com/martincostello/github-automation "GitHub Automation repository on GitHub"
[github-cli]: https://cli.github.com/ "GitHub CLI"
[github-webhook-push]: https://docs.github.com/webhooks-and-events/webhooks/webhook-events-and-payloads#push "push webhook event on GitHub"
[improvement-commit]: https://github.com/martincostello/github-automation/commit/5cb88f4805e6181ec4a6ace53a6164ca524e21af "Run SDK update for previews - martincostello/github-automation@5cb88f4"
[is-it-worth-the-time]: https://xkcd.com/1205/ "Is It Worth the Time? on XKCD"
[npm-updates]: https://github.com/martincostello/costellobot/blob/8a4352c5938f1c373ea8f64194bc352b5c243ed4/.github/workflows/update-dotnet-sdk.yml#L21-L82 "Updating npm packages"
[part-1]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-1-why-upgrade "Why Upgrade?"
[part-3]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-3-previews-1-to-5 "Previews 1-5"
[part-4]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-4-preview-6 "Preview 6"
[part-5]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-5-preview-7-and-rc-1-2 "Preview 7 and Release Candidates 1 and 2"
[part-6]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-6-stable-release "The Stable Release"
[patch-tuesday]: https://en.wikipedia.org/wiki/Patch_Tuesday "Patch Tuesday on Wikipedia"
[permanence]: https://xkcd.com/910/ "Permanence on XKCD"
[rebase-workflow]: https://github.com/martincostello/github-automation/blob/main/.github/workflows/rebase.yml "The rebase workflow on GitHub"
[rebaser]: https://github.com/martincostello/github-automation/blob/main/src/Rebaser/Program.cs "Rebaser on GitHub"
[shields-io]: https://shields.io/badges "shields.io badges"
[update-dotnet-sdk]: https://github.com/marketplace/actions/update-net-sdk "martincostello/update-dotnet-sdk on the GitHub Actions Marketplace"
[update-dotnet-sdks]: https://github.com/martincostello/github-automation/blob/main/.github/workflows/update-dotnet-sdks.yml "update-dotnet-sdks workflow on GitHub"
[upgrade-report-sample]: https://github.com/martincostello/github-automation/actions/runs/5506134751 "Example .NET vNext Upgrade Report"
[upgrade-report-workflow]: https://github.com/martincostello/github-automation/blob/main/.github/workflows/dotnet-upgrade-report.yml "dotnet-upgrade-report workflow on GitHub"
