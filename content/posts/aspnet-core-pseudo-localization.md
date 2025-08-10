---
title: Pseudo-localization with ASP.NET Core
date: 2018-12-17
tags: aspnetcore,dotnet,localization,pseudo localization,testing
layout: post
description: "Using pseudo-localization with ASP.NET Core to help test your application is ready for globalization, internationalization and localization."
---

Earlier this year I read a [blog post](https://medium.com/netflix-techblog/pseudo-localization-netflix-12fff76fbcbe "Pseudo Localization @ Netflix") by [Tim Brandall](https://www.linkedin.com/in/timjbrandall/ "Tim Brandall on LinkedIn") at Netflix about how they use pseudo-localization to test the User Interfaces of their various native applications for layout issues. For example, text in languages such as German and Finnish can be up to 40% longer than their English equivalents, causing text overflow in UI elements that don't account for such differences.

A simple example of pseudo-localisation would be changing the text of the sentence below.

> _The quick brown fox jumped over the lazy dog_

With transformations to lengthen the text, apply accents and surround it in brackets applied, it becomes:

> _[Ţĥéẋ ǫûîçķẋẋ ƀŕöŵñẋẋ ƒöẋẋ ĵûɱþéðẋẋ öṽéŕẋẋ ţĥéẋ ļåžýẋẋ ðöĝẋẋ]_

I found the approach particularly interesting, so wondered how I could look at using it in my own day-to-day work.

<!--more-->

Before this year I'd not particularly worked on a large-scale application where its users could speak one of several different languages, but as part of my role at [Just Eat](https://careers.just-eat.com/departments/technology "Just Eat Technology") that's no longer the case.

The team I'm a member of has been working on a new updated experience for our consumer-facing website which needs to be global for all of our core markets and localised for the languages that the majority of our consumers speak in those markets. This includes variants of English for the UK, Ireland and Australia, as well as Danish, French, Italian, Norwegian and Spanish for our other core markets.

The application my team maintains that requires such localisation is an ASP.NET Core web application that uses MVC with Razor views, so to help successfully deliver a high-quality experience for all of its users regardless of the language they speak, I thought that I'd investigate what tools were available to incorporate pseudo-localisation (which from here on I'll refer to as _"p16n"_ for ease) into our development workflow.

## Existing Tools

Before trying to re-invent the wheel, I thought I'd see what tools and processes were already available in the .NET/C# space for p16n (and localisation generally).

### Pseudolocalizer

The first tool I found was a Windows console application written in 2012 by [Anders Kaplan](https://github.com/anderskaplan "Anders Kaplan on GitHub.com") called [_Pseudolocalizer_](https://github.com/anderskaplan/Pseudolocalizer "Pseudolocalizer repository on GitHub.com").

The tool contains five built-in transformations for p16n and can operate on existing `.resx` files (the default [XML-based localisation file format](https://docs.microsoft.com/en-us/dotnet/framework/resources/creating-resource-files-for-desktop-apps#resources-in-resx-files "Creating Resource Files for Desktop Apps") for .NET) to produce transformed text in a pseudo-locale with a language code of `qps-ploc` (which I'll come back to later).

Unfortunately due to its age, it only supports usage for .NET Framework and Windows, and is not available for download as a pre-compiled binary, only as MIT-licensed source code in GitHub.

### Windows Pseudo-locales

Reading up on `qps-ploc` from Pseudolocalizer lead me to an [article on MSDN](https://docs.microsoft.com/en-gb/windows/desktop/Intl/using-pseudo-locales-for-localization-testing "Using pseudo-locales for localizability testing") about four pseudo-locales that are built into the Windows operating system itself to aid such testing.

This means that using the `"qps-ploc"` culture with the .NET `CultureInfo` class enables p16n to be automatically applied to string formatting operations on values of types such as `DateTime`, `int`, `TimeSpan` etc.

That's definitely useful, but falls far short of a full solution. After all, very few application's user interfaces consist solely of numbers and dates with no other accompanying labels or description.

### The XLIFF File Format and xliff-tasks

While researching this, I also stumbled across how the .NET Core SDK tooling is localised [on GitHub](https://github.com/dotnet/sdk/tree/00c2243357023efc76c9ad5c6d12a96746a23bd5/src/Tasks/Common/Resources/xlf "XLIFF resource files for the .NET Core SDK"), which uses a file format called [XLIFF](https://en.wikipedia.org/wiki/XLIFF "XLIFF on Wikipedia"), which stands for _"XML Localization Interchange File Format"_.

This file format is an industry standard for managing translation of text, and contains metadata for things like translations' state (translated, new, in need of review etc.), which makes it easier to manipulate and maintain with tooling compared to the `.resx` format.

Digging through the open-source .NET Core repositories on GitHub lead me to [a repository](https://github.com/dotnet/xliff-tasks "xliff-tasks on GitHub.com") for a set of MSBuild-based tasks that operate on a source `.resx` file to generate `.xlf` files for all the languages required that can then be submitted for translation. It's also helpfully published in the public .NET Core [MyGet](https://dotnet.myget.org/feed/dotnet-core/package/nuget/XliffTasks "xliff-tasks on the .NET Core MyGet gallery") feed.

This tooling also detects drift between the source strings (e.g. English) and the translations. This means that if the meaning of the original text is changed, metadata can be emitted into the `.xlf` file(s) warning of the drift and that the translation may need updating.

Using the `.xlf` files as the compilation source, rather than `.resx` file(s), the tooling then integrates with the C# compiler to produce [satellite resource assemblies](https://docs.microsoft.com/en-us/dotnet/framework/resources/creating-satellite-assemblies-for-desktop-apps "Creating Satellite Assemblies for Desktop Apps") for each of the languages being targeted for use at runtime in the application.

This sounds useful to come back to for a full implementation in due course for a production application, so I'll talk about it again later in this post.

## A Proof of Concept

So with a tool to generate p16n strings and some operating system support, I thought I'd have a go at manually generating a `.resx` file for `qps-ploc` and trying it out in the [companion website](https://github.com/martincostello/alexa-london-travel-site "alexa-london-travel-site on GitHub.com") for my [Alexa skill](https://www.amazon.co.uk/dp/B01NB0T86R "London Travel on amazon.co.uk").

I cloned Pseudolocalizer locally, compiled it and ran it against the [`.resx`](https://github.com/martincostello/alexa-london-travel-site/blob/6ed8488ccdc110308d06adedb9fa63a65db8b05e/src/LondonTravel.Site/SiteResources.resx "SiteResources.resx for alexa-london-travel-site on GitHub.com") file in my site's repository, set the request language to `qps-ploc` and compiled and debugged my application to find...normal English text.

I was perplexed - the p16n text wasn't there. Half an hour of investigation later, I discovered why - the `qps-ploc` satellite resource assembly [wasn't there](https://github.com/Microsoft/msbuild/issues/3653 "Cannot compile satellite resource assemblies for Windows' pseudo-locales").

Looking into the source code of MSBuild (isn't open source software great? ❤️) I found that for performance reasons, the valid cultures are cached and compared against a hard-coded list. The list didn't include the pseudo-locales of Windows, so MSBuild considered the locale code invalid and ignored it from compilation.

So I had some bits of tooling, but the compiler "feature" coupled with the lack of a compiled distribution of Pseudolocalizer meant that I didn't have a workable process for usage for a production business application.

## Updating the Approach

With the pieces of the puzzle I'd found all being open source, all of the problems mentioned above were surmountable. This meant it was time to get writing some code (and tests) to get a working process ready.

### Forking and Modernising Pseudolocalizer

Given that Anders Kaplan seems to be pretty inactive on GitHub.com these days and that there has been no activity in the repository for nearly 6 years, I decided to fork the repository and make my own updates to the tool for the goal I wanted to achieve, rather than submit them back via a Pull Request.

You can find it here: [https://github.com/martincostello/Pseudolocalizer](https://github.com/martincostello/Pseudolocalizer)

This included:

- Updates to target .NET Core and allow usage on Linux and macOS.
- Support for processing `.xlf` files.
- [Packaging the application](https://www.nuget.org/packages/PseudoLocalize/ "PseudoLocalize on NuGet.org") as a [.NET Core Global Tool](https://docs.microsoft.com/en-us/dotnet/core/tools/global-tools ".NET Core Global Tools overview").
- Making the core string-transformations available as library on [NuGet](https://www.nuget.org/packages/PseudoLocalizer.Core/ "PseudoLocalizer.Core on NuGet.org").
- Creating an [integration](https://www.nuget.org/packages/PseudoLocalizer.Humanizer/ "PseudoLocalizer.Humanizer on NuGet.org") with [Humanizer](https://github.com/Humanizr/Humanizer "Humanizer on GitHub.com").
- Fixing transforms for strings with formatting placeholders and embedded HTML.

### Generating a qps-ploc Satellite Resource Assembly

Fixing MSBuild so that the p16n resource DLLs were generated by the compiler was fairly simple, so I submitted a [Pull Request](https://github.com/Microsoft/msbuild/pull/3654 "Support Windows' pseudo-locales") to add support for it.

The Microsoft team reviewed, gave feedback and merged the change within less than a week, and the fix itself shipped as part of [Visual Studio 15.9](https://docs.microsoft.com/en-us/visualstudio/releasenotes/vs2017-relnotes "Visual Studio 2017 release notes") in November 2018.

## Putting Pseudo-localization into Practice

With the launch of Visual Studio 15.9 and PseudoLocalize being available from NuGet.org, it was possible to put p16n to use in to the ASP.NET Core application my team maintains to help us with testing the globalisation and localisation of the application.

I also integrated it my Alexa skill's companion site ([Pull Request](https://github.com/martincostello/alexa-london-travel-site/pull/212 "Add support for pseudo-localisation")).

Finally, I made a simple sample application illustrating how to add p16n to an ASP.NET Core 2.2 MVC application that demonstrates it in use.

It's available here on GitHub under the Apache-2.0 licence: [https://github.com/martincostello/aspnet-core-pseudo-localization](https://github.com/martincostello/aspnet-core-pseudo-localization)

### Example and Walkthrough

The example is a simple Todo application that stores a list of tasks in memory that can be added, marked as complete and deleted. It uses Humanizer to show the age of tasks, and as it accepts user input it shows both site-provided content, which is localised, and user-provided content, which is left as-is.

_Apologies if you speak French, German, Japanese or Spanish and the text isn't...quite right - all the text was generated by putting the English text into Google Translate._

Below is a screenshot of the application when set to use UK English.

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_todoapp-english.png" alt="Todo application in UK English" title="Todo application in UK English">

Below is a screenshot of the application when the language is changed to the pseudo-locale. Notice that all of the text has been updated except for the user-provided Todo items' descriptions. Even the operating system-formatted date has been changed.

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_todoapp-pseudo.png" alt="Todo application in pseudo-localized English" title="Todo application in pseudo-localized English">

You can find the source `.resx` file [here](https://github.com/martincostello/aspnet-core-pseudo-localization/blob/main/src/TodoApp/Resources.resx "Resources.resx") and the `.xlf` files [here](https://github.com/martincostello/aspnet-core-pseudo-localization/tree/main/src/TodoApp/xlf "xlf files").

Below are the relevant steps for wiring p16n into the application.

#### Add the MyGet feed

Add the .NET Core MyGet feed to [`nuget.config`](https://github.com/martincostello/aspnet-core-pseudo-localization/blob/c2981c8ed7f5fee9e64e85705a420419c91af230/NuGet.config#L5) for the `xliff-tasks` package

```
<add key="dotnet-core" value="https://dotnet.myget.org/F/dotnet-core/api/v3/index.json" />
```

#### Add the xliff-tasks package

Install the [xliff-tasks](https://github.com/martincostello/aspnet-core-pseudo-localization/blob/c2981c8ed7f5fee9e64e85705a420419c91af230/src/TodoApp/TodoApp.csproj#L14) NuGet package.

```
<PackageReference Include="XliffTasks" Version="0.2.0-beta-63125-01" PrivateAssets="All" />
```

#### Specify the required languages

Configure [the languages](https://github.com/martincostello/aspnet-core-pseudo-localization/blob/c2981c8ed7f5fee9e64e85705a420419c91af230/src/TodoApp/TodoApp.csproj#L7) to generate `.xlf` files for.

```
<XlfLanguages>de-DE;en-GB;en-US;es-ES;fr-FR;ja-JP;qps-Ploc</XlfLanguages>
```

#### Configure qps-Ploc for requests

Add [`qps-Ploc`](https://github.com/martincostello/aspnet-core-pseudo-localization/blob/c2981c8ed7f5fee9e64e85705a420419c91af230/src/TodoApp/Startup.cs#L79) as a supported request language.

Note the capitalisation of the P in the culture code here. On non-Windows platforms, this is not a known culture, so the default casing rules are applied internally to force `qps-ploc` to `qps-Ploc`, which can then cause issues with case-sensitive file systems such as Linux.

Using the normalised casing prevents failures on Linux and macOS due while still working as expected on Windows.

```
supportedCultures.Add(new CultureInfo("qps-Ploc"));
```

#### Add p16n for Humanizer

Install the [NuGet package](https://github.com/martincostello/aspnet-core-pseudo-localization/blob/c2981c8ed7f5fee9e64e85705a420419c91af230/src/TodoApp/TodoApp.csproj#L13).

```
<PackageReference Include="PseudoLocalizer.Humanizer" Version="0.1.0" />
```

Configure [support](https://github.com/martincostello/aspnet-core-pseudo-localization/blob/c2981c8ed7f5fee9e64e85705a420419c91af230/src/TodoApp/Startup.cs#L81-L83) for `qps-Ploc`.

```
new PseudoLocalizer.Humanizer.PseudoHumanizer().Register();
```

#### Regenerate p16n Text When Source Changed

Add a custom MSBuild [task](https://github.com/martincostello/aspnet-core-pseudo-localization/blob/c2981c8ed7f5fee9e64e85705a420419c91af230/src/TodoApp/TodoApp.csproj#L31-L44) to the project file so that PseudoLocalize is invoked to regenerate the `qps-Ploc` strings whenever the `.resx` file is updated (e.g. by running `dotnet msbuild /t:UpdateXlf`).

```
<ItemGroup>
  <_PseudoLocalizedFiles Include="$(MSBuildThisFileDirectory)xlf\*.qps-Ploc.xlf" />
</ItemGroup>
<PropertyGroup>
  <_PseudoLocalizeInstalledCommand Condition=" '$(OS)' == 'Windows_NT' ">
      where pseudo-localize
  </_PseudoLocalizeInstalledCommand>
  <_PseudoLocalizeInstalledCommand Condition=" '$(OS)' != 'Windows_NT' ">
      which pseudo-localize
  </_PseudoLocalizeInstalledCommand>
</PropertyGroup>
<Target Name="UpdatePseudoLocalization" AfterTargets="UpdateXlf">
  <Exec
    Command="$(_PseudoLocalizeInstalledCommand)"
    ConsoleToMsBuild="true"
    IgnoreExitCode="true"
    StandardErrorImportance="Normal"
    StandardOutputImportance="Normal">
    <Output TaskParameter="ExitCode" PropertyName="_PseudoLocalizeInstalled" />
  </Exec>
  <Warning
    Condition=" $(_PseudoLocalizeInstalled) != 0 "
    Text="The PseudoLocalize .NET Core Global Tool is not installed." />
  <Warning
    Condition=" $(_PseudoLocalizeInstalled) != 0 "
    Text="To install this tool, run the following command: dotnet tool install --global PseudoLocalize" />
  <Exec
    Condition=" $(_PseudoLocalizeInstalled) == 0 "
    Command="pseudo-localize %(_PseudoLocalizedFiles.Identity) --overwrite --force"
    ConsoleToMsBuild="true"
    StandardOutputImportance="Normal" />
</Target>
```

## Conclusion

It took a few months of occasional work between the initial idea and being able to use it in a production scenario, but it took just a few days of effort to update and publish PseudoLocalize and fix the compiler support to be able to plug p16n into an ASP.NET Core application.

It's also now available for use in the application my team maintains for checking for layout issues with UI localisation.

It was interesting to learn about, and a fun challenge to get working. I hope you find the information in this blog post useful and informative for adding pseudo-localization to your own ASP.NET Core applications!

## Links

- [Pseudolocalizer](https://github.com/martincostello/Pseudolocalizer)
- [Sample application](https://github.com/martincostello/aspnet-core-pseudo-localization)
- [Windows Pseudo-locales](https://docs.microsoft.com/en-gb/windows/desktop/Intl/using-pseudo-locales-for-localization-testing)
