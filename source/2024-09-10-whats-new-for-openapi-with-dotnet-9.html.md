---
title: "What's New for OpenAPI with .NET 9"
date: 2024-09-10
tags: dotnet,openapi,swagger,swashbuckle
layout: bloglayout
description: "A look at the new Microsoft.AspNetCore.OpenApi package in .NET 9 and comparing it to NSwag and Swashbuckle.AspNetCore."
image: "https://cdn.martincostello.com/blog_openapi.png"
---

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_openapi.png" alt="The OpenAPI logo" title="The OpenAPI logo" height="272px" width="899x">

Developers in the .NET ecosystem have been writing APIs with ASP.NET and ASP.NET Core for years, and
[OpenAPI][openapi] (née Swagger) has been a popular choice for documenting those APIs.
OpenAPI at its core is a machine-readable document that describes the endpoints available in an API.
It contains information not only about parameters, requests and responses, but also additional metadata
such as descriptions of properties, security-related metadata, and more.

These documents can then be consumed by tools such as [Swagger UI][swagger-ui] to provide a user interface
for developers to interact with the API quickly and easily, such as when testing. With the recent surge in
popularity of AI-based development tools, OpenAPI has become even more important as a way to describe APIs
in a way that machines can understand.

For a long time, the two most common libraries to produce API specifications at runtime for ASP.NET Core
have been [NSwag][nswag] and [Swashbuckle][swashbuckle]. Both libraries provide functionality that allows
developers to generate a rich OpenAPI document(s) for their APIs in either JSON and/or YAML from their
existing code. The endpoints can then be augmented in different ways, such as with attributes or custom
code, to further enrich the generated document(s) to provide a great Developer Experience for its consumers.

With the upcoming release of [ASP.NET Core 9][aspnetcore-9], the ASP.NET team have introduced new functionality
for the existing [Microsoft.AspNetCore.OpenApi NuGet package][microsoft-aspnetcore-openapi], that provides a
new way to generate OpenAPI documents for ASP.NET Core Minimal APIs.

In this post, we'll take a look at the new functionality and compare it to the exsisting NSwag and Swashbuckle
libraries to see how it compares in both features as well as performance.

READMORE

## Why a new OpenAPI library?

You may wonder why when there's two existing and popular solutions for generating OpenAPI documents in ASP.NET Core
that there's a need for a third new option to enter the fray. While both NSwag and Swashbuckle have served the community
well for many years, recently both libraries have seen a decline in maintenance and updates. This has led to a lag
in the ability for new features of the framework to be leveraged and/or supported in these libraries with each new release.

While Swashbuckle has had a bit of a resurgence in 2024 with the [announcement of new maintainers for the project][swashbuckle-maintainers]
(I'm one of them 👋) and now has first-class support for .NET 8, it is still an open source project that is provided
for free and maintained by volunteers in their spare time. With these constraints, it's difficult to keep up with the
pace of change in the .NET ecosystem with a new major release every year. By contrast, the ASP.NET team at Microsoft
are paid to work on the framework full-time, so can dedicate time to ensure that the libraries they provide are kept
up-to-date with the latest features and best practices as the product evolves over time.

Another motivating factor for the new library is that [native AoT compilation][native-aot] is becoming an increasingly
popular way to deploy .NET applications, especially in the cloud, where reducing cold start times is important for
high-scale applications with variable load patterns. Both NSwag and Swashbuckle rely heavily on reflection to generate
their OpenAPI documents, but reflection has many constraints when used in an application compiled to run as native code.
This makes many existing code patterns in these libraries not work due to the metadata needed being trimmed away, as it
appears to be unused.

While both libraries probably _could_ be refactored to support native AoT, this would be a significant amount of work to
undertake as it would require a significant rewrite of the core functionality of both libraries. Speaking as a Swashbuckle
maintainer, the amount of work required is so large compared to the benefits it would provide, that it's not something that
is realistically going to happen.

A brand new library that is designed from the ground-up to support native AoT compilation and the latest features of ASP.NET
Core however is a very different proposition. Any new library is unburdened by the weight of its existing functionality,
and instead can start fresh with a new design that is more suited to the current state of the ASP.NET Core ecosystem and
its needs in 2024 and beyond.

The Swashbuckle maintainers are also unconcerned that there's a new library on the scene. We don't consider it to be a
competitor to Swashbuckle - for example, the new library only supports ASP.NET Core 9 and later, whereas Swashbuckle has
a broader range of support for older versions of ASP.NET Core, including for .NET Framework. Users who want to use the
new functionality and wish to migrate are welcome to do so, but we're not going to stop maintaining Swashbuckle any time
soon. I'm sure many developers are happy with their existing library of choice and will continue to use it rather than invest
time and effort moving from one library to another.

## Microsoft.AspNetCore.OpenApi Features

TODO

- Transformers
- Endpoints (compose with auth etc)
- No UI
- No YAML
- No XML comments ([yet][aspnetcore-openapi-xml])
- Native AoT support
- CLI generation

## Comparison with NSwag and Swashbuckle

TODO

## Summary

TODO

[aspnetcore-9]: https://learn.microsoft.com/aspnet/core/release-notes/aspnetcore-9.0 "What's new in ASP.NET Core 9.0"
[aspnetcore-openapi]: https://learn.microsoft.com/aspnet/core/fundamentals/openapi/aspnetcore-openapi?view=aspnetcore-9.0 "Work with OpenAPI documents on Microsoft Learn"
[aspnetcore-openapi-stream-1]: https://www.youtube.com/watch/XoMese9g8WQ "ASP.NET Community Standup - OpenAPI Updates in .NET 9 on YouTube"
[aspnetcore-openapi-stream-2]: https://www.youtube.com/watch/keK69Y5HqvY "ASP.NET Community Standup - OpenAPI Updates in .NET 9 (Part 2) on YouTube"
[aspnetcore-openapi-xml]: https://github.com/dotnet/aspnetcore/issues/39927#issuecomment-2233634912 "Support XML-based OpenAPI docs for minimal APIs"
[microsoft-aspnetcore-openapi]: https://www.nuget.org/packages/Microsoft.AspNetCore.OpenApi "The Microsoft.AspNetCore.OpenApi package on NuGet.org"
[microsoft-openapi]: https://github.com/microsoft/OpenAPI.NET "The OpenAPI.NET repository on GitHub"
[native-aot]: https://learn.microsoft.com/dotnet/core/deploying/native-aot "Native AOT deployment"
[nswag]: https://github.com/RicoSuter/NSwag "The NSwag repository on GitHub"
[openapi]: https://swagger.io/docs/specification/about/ "What Is OpenAPI?"
[openapi-comparisons]: https://github.com/martincostello/aspnetcore-openapi "A GitHub repository comparing OpenAPI implementations for ASP.NET Core"
[openapi-extensions]: https://github.com/martincostello/openapi-extensions "The OpenAPI Extensions repository on GitHub"
[openapi-specification]: https://swagger.io/specification/ "The OpenAPI specification"
[safia-abdalla]: https://github.com/captainsafia "@captainsafia on GitHub"
[swagger-ui]: https://github.com/swagger-api/swagger-ui "The Swagger UI repository on GitHub"
[swashbuckle]: https://github.com/domaindrivendev/Swashbuckle.AspNetCore "The Swashbuckle.AspNetCore repository on GitHub"
[swashbuckle-maintainers]: https://github.com/domaindrivendev/Swashbuckle.AspNetCore/discussions/2778 "Swashbuckle.AspNetCore maintainers announcement"
[swashbuckle-ui]: https://www.nuget.org/packages/Swashbuckle.AspNetCore.SwaggerUI ""The Swashbuckle.AspNetCore.SwaggerUI package on NuGet.org"
