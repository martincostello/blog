---
title: SQL LocalDB Wrapper v2 - The Next Generation
date: 2018-10-02
tags: localdb,sql,testing
layout: post
description: "SQL LocalDB Wrapper version 2.0 has been released to NuGet, adding support for .NET Standard 2.0."
---

After over 2 years and 100,000 package downloads since the [last release](https://www.nuget.org/packages/System.Data.SqlLocalDb/1.15.0 "System.Data.SqlLocalDb 1.15.0 on NuGet.org") of [SQL LocalDB Wrapper](https://github.com/martincostello/sqllocaldb "SQL LocalDB Wrapper on GitHub.com"), I've released [version 2](https://github.com/martincostello/sqllocaldb/releases/tag/v2.0.0 "SQL LocalDB Wrapper v2.0.0 on GitHub.com") to NuGet.

Version 2.0.0 of SQL LocalDB Wrapper is a major rewrite of version 1.x.x, and is now fully .NET Core compatible. You can read more about the changes in the [release notes](https://github.com/martincostello/sqllocaldb/releases/tag/v2.0.0 "SQL LocalDB Wrapper 2.0.0 release notes").

- [NuGet package](https://www.nuget.org/packages/MartinCostello.SqlLocalDb "MartinCostello.SqlLocalDb on NuGet.org")
- [GitHub repository](https://github.com/martincostello/sqllocaldb "SQL LocalDB Wrapper on GitHub.com")

<!--more-->

## Origins

SQL LocalDB Wrapper was my first ever open-source project, first published in 2012 as a ZIP file with the source code on [CodePlex](https://archive.codeplex.com/) with TFS source control. Since then it's moved to Git, moved to [GitHub](https://github.com/martincostello/sqllocaldb "SQL LocalDB Wrapper on GitHub.com"), and been published as a [NuGet package](https://www.nuget.org/packages/System.Data.SqlLocalDb "System.Data.SqlLocalDb NuGet.org").

SQL LocalDB Wrapper was born from a desire at a previous role when I was in a quality assurance role to find a way to perform more realistic automated testing of data access code for SQL Server. Reliably provisioning a throw-away SQL Server instance before the days of Docker and containers was fraught with difficulty. SQL Server was complicated to install, it wasn't fast to do so, and was incredibly difficult to automate, even if it was just the SQL Server Express edition!

With the release of SQL Server LocalDB, with just a simple MSI installation onto a CI build server, a SQL Server instance could be created and deleted at will in just a few seconds with almost full fidelity of SQL Server language features available for use.

The problem was that managing the LocalDB instances needed to perform commands using the `sqllocaldb.exe` tool, not making it particularly easy to integrate with. SQL Server LocalDB does come with an API for programmatic usage, but this a native C DLL for Windows that has to be located using the registry.

Thus SQL LocalDB Wrapper was born. It hides all that complexity from you, providing a simple API for managing instances and getting the connection strings to connect to them.

## Version 2

Since version 1.15 was released, .NET Framework 3.5, 4.0 and 4.5 have all reached end-of-life, SQL Server now runs on Linux, and .NET Core has shipped two major releases. The .NET development landscape is quite different, even more so since I first made version 1.0 available!

The library still gets usage from people and downloads from NuGet, so I figured that I should update it to support .NET Core quite a while ago. However, that effort lay stagnant and on the back-burner for a while due to the fact that SQL Server LocalDB is still Windows-specific and needs access to the Windows Registry to find the native library it wraps.

Things changed with the release of .NET Core 2.0 though, with Microsoft adding various APIs and compatibility packs to add support for various platform-specific features that don't necessarily fully fit with the cross-platform ideals of .NET Core.

With those changes, I finally got the time a few months ago to put the time into updating the library for supporting .NET Core. This wasn't the simplest task as things like `System.Configuration` support, functionality related to EntityFramework, and use of MSTest, all having to be shed to support the goal of 1st class .NET Core support for the library. There was also the matter of updating all the documentation and samples, as well as writing a [migration guide](https://github.com/martincostello/sqllocaldb/wiki/Migrating-to-MartinCostello.SqlLocalDb-from-System.Data.SqlLocalDb "Migrating to MartinCostello.SqlLocalDb from System.Data.SqlLocalDb").

With such large amounts of refactoring required, I also took it upon myself to revisit the API surface and functionality, and rework some design decisions based on the skills and experience I've picked up in the 6 years since I started the wrapper. Overall it's take about 3 months to get everything migrated, updated, documented and tested.

Of course, it is still only fully functional on Windows due to the nature of SQL Server LocalDB itself, but it will happily sit within a cross-platform application, with an API surface that allows support to be tested for at runtime on any platform for light-up of functionality.

You can find [samples](https://github.com/martincostello/sqllocaldb/tree/main/samples "SQL LocalDB Wrapper samples") for using the library in the GitHub repository, with examples for writing tests that use it as well as some usage within a simple application.

I imagine over time the need for this library will decline as SQL Server LocalDB becomes more cross-platform and other technologies like containers become even more wide-spread, but I'm glad to keep it current and fresh for the next generation of C# and .NET for anyone who might find it useful.
