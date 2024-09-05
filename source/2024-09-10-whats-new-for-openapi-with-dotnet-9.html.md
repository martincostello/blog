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

## Microsoft.AspNetCore.OpenApi Features (and Gaps)

At a high-level, the new Microsoft.AspNetCore.OpenApi package has the same basic functionality as both NSwag and Swashbuckle.
It generates an OpenAPI document for your ASP.NET Core endpoints at runtime. The shape of your endpoints, such as their methods,
paths, requests, responses, parameters etc. are all derived from your application's code. The declaration can be extended with
metadata, such as with attributes like `[ProducesResponseType]` and `[Tags]`, to provide additional information to the
generation process to describe the endpoints and schemas as required for your needs.

The library also integrates with the existing [Microsoft.Extensions.ApiDescription.Server package][m-e-apidescription-server]
to generate the document at build time via a custom MSBuild target that can run as part of compiling your project to produce
the OpenAPI document as a file on disk. This is useful for CI/CD scenarios like [linting][linting] - for example you could run
[spectral][spectral] as part of your build pipeline to validate that your OpenAPI document is valid and follows recommended best practices.

Like Swashbuckle, the package is built on top of the [OpenAPI.NET][microsoft-openapi] library, which provides the C# types
for the various primitives of the [OpenAPI specification][openapi-specification]. The advantage of this is that adding support
for new versions of the OpenAPI specification in the future (e.g. OpenAPI 3.1) should be easier as the library can be updated
to use a new version that supports it in the future, with only the "glue" for generating the types from the endpoints needing
to be updating, rather than also needing to fully implement the specification itself.

OpenAPI support is added at the [endpoint][aspnetcore-endpoints] level (think `MapGet()` and similar methods). This allow the
the OpenAPI document can be coupled into other mechanisms in ASP.NET Core, such as authorization, caching, and more.

As noted earlier, it is also fully compatible with native AoT, allowing you to generate OpenAPI documents for your ASP.NET Core
applications at runtime even when compiled to native code, such as when running in a container, if you want to expose your
API documentation to your users in your deployed environment.

To add the minimal level of support for generating an OpenAPI document, you could add the following code to your ASP.NET Core
application after adding a reference to the Microsoft.AspNetCore.OpenApi NuGet package:

```
var builder = WebApplication.CreateBuilder();

// Add services for generating OpenAPI documents
builder.Services.AddOpenApi();

var app = builder.Build();

// Add the endpoint to get the OpenAPI document
app.MapOpenApi();

// Your API endpoints
app.MapGet("/", () => "Hello world!");

app.Run();
```

Running the server and navigating to the `/openapi/v1/openapi.json` URL in a browser will then return a OpenAPI document as JSON
that describes the endpoints in your application.

### Transformers

If (or when) you need to enrich the document further, the library provides a number of extensions points that you can use to
extend the document, or individual operations and/or schemas, using the concept of _transformers_. Transformers provide a way
for you to run custom code to modify the OpenAPI document as it is being generated, allowing you to add additional metadata.

Transformers can either be registered as inline delegates or as types that implement the appropriate transformer interface
(`IOpenApiDocumentTransformer`, `IOpenApiOperationTransformer` or `IOpenApiSchemaTransformer`). In the case of the interfaces,
this allows you to implement types that use various additional services (e.g. `IConfiguration`) in your implementations and
means they can be resolved from the dependency injection container used by your application.

Here's an example of declaring and then using a document transformer:

```
// Add a custom service to the DI container
builder.Services.AddTransient<IMyService, MyService>();

// Add services for generating OpenAPI documents and register a custom document transformer
builder.Services.AddOpenApi(options =>
{
    options.AddDocumentTransformer<MyDocumentTransformer>();
});

// A custom implementation of IOpenApiDocumentTransformer that uses our custom service.
// The type is activated from the DI container, so can use other services in the application.
class MyDocumentTransformer(IMyService myService) : IOpenApiDocumentTransformer
{
    public async Task TransformAsync(
        OpenApiDocument document,
        OpenApiDocumentTransformerContext context,
        CancellationToken cancellationToken)
    {
        // Use myService to modify the document in some way...
    }
}
```

As another example of the power of these transformers, I've built a library of my own on top of these abstractions to add
additional capabilities for my own APIs. The [OpenAPI Extensions for ASP.NET Core][openapi-extensions] library provides a
number of transformers that can be used to add additional metadata to the OpenAPI document, such as support for generating
rich examples for requests, responses and schemas.

### Feature Gaps

As a first release however, there are a few feature gaps compared to what developers may come to expect from an OpenAPI
solution compared to NSwag and Swashbuckle.

#### No User Interface

Compared to the application templates that shipped with the .NET SDK in previous releases of ASP.NET Core, there is no
built-in solution to render a user interface on top of the OpenAPI document that is generated.

I don't think this is a major gap at this stage, as it's still possible to add a Swagger UI to your application with ease
by continuing to use the [Swashbuckle.AspNetCore.SwaggerUI][swashbuckle-ui] NuGet package to provide one. This NuGet package
is independent from the rest of Swashbuckle, so can be used with the new OpenAPI library without any issues or bloat from
including two implementations. From version 6.6.2 of Swashbuckle.AspNetCore, this package also supports native AoT, so
doesn't compromise support for that either.

#### No XML Comments

For the .NET 9 release, there is no support for adding descriptions to the OpenAPI document from the XML documentation in
your code. This is a feature that is [planned for a future release][aspnetcore-openapi-xml], likely .NET 10, but a preview
of the feature is expected to be made available at some point before then.

If this is critical for your application, you could investigate creating your own transformer to consume your XML
documentation until then.

#### No support for YAML documents

While both the Microsoft.OpenApi library and NSwag support generating OpenAPI documents in YAML (unlike Swashbuckle), the
Microsoft.AspNetCore.OpenApi package currently only supports generating OpenAPI documents in JSON. This is a feature that
could be added in a future release.

This is again another piece of functionality I've added to my [OpenAPI Extensions for ASP.NET Core][openapi-extensions] library, so you
could use that to generate YAML documents if you need to. It's enabled with a single line of code in your application:

```
app.MapOpenApiYaml();
```

## Comparison with NSwag and Swashbuckle

So how does the new Microsoft.AspNetCore.OpenApi package compare to the existing NSwag and Swashbuckle libraries?

While the goal of the library is not for 100% feature parity with either of the existing libraries, it does provide the
majority of the same functionality that developers would expect from an OpenAPI library for ASP.NET Core applications.
As noted above, the core gaps are support for XML comments and a built-in User Interface.

If you'd like a more detailed comparison of the three libraries, you can check out this [GitHub repository][openapi-comparisons]
that implements a Todo API and exposes equivalent OpenAPI documents for it using all three libraries. This should give you
a good idea of how all three libraries express the same concepts and how you use them as an application developer.

As an example, here's the code to add an OpenAPI document and customise the API info in all three implementations.

One thing you'll notice that the same ability to customise the document is done through either similar concepts that are
named either _transformers_ (ASP.NET Core), _processors_ (NSwag) or _filters_ (Swashbuckle).

### Microsoft.AspNetCore.OpenApi

```
public static IServiceCollection AddAspNetCoreOpenApi(this IServiceCollection services)
{
    services.AddOpenApi(options =>
    {
        options.AddDocumentTransformer((document, _, _) =>
        {
            document.Info.Title = "Todo API";
            document.Info.Description = "An API for managing Todo items.";
            document.Info.Version = "v1";

            return Task.CompletedTask;
        });

        options.AddOperationTransformer(new AddExamplesTransformer());
    });

    return services;
}
```

[Code][example-aspnetcore]

### NSwag

```
public static IServiceCollection AddNSwagOpenApi(this IServiceCollection services)
{
    services.AddOpenApiDocument(options =>
    {
        options.Title = "Todo API";
        options.Description = "An API for managing Todo items.";
        options.Version = "v1";

        options.OperationProcessors.Add(new AddExamplesProcessor());
    });

    return services;
}
```

[Code][example-nswag]

### Swashbuckle

```
public static IServiceCollection AddSwashbuckleOpenApi(this IServiceCollection services)
{
    services.AddSwaggerGen(options =>
    {
        var info = new OpenApiInfo
        {
            Title = "Todo API",
            Description = "An API for managing Todo items.",
            Version = "v1"
        };

        options.SwaggerDoc(info.Version, info);
        options.OperationFilter<AddExamplesFilter>();
    });

    return services;
}
```

[Code][example-swashbuckle]

## How does it work?

TODO (Maybe)

## Performance

TODO - TL;DR: It's faster than NSwag and Swashbuckle as of RC1.

## Further Reading

For more information on the new features in the Microsoft.AspNetCore.OpenApi package, check out these ASP.NET Community Standup
streams on YouTube. Here [Safia Abdalla][safia-abdalla], the engineer behind this new functionality, explains the new features
in the package and how to use them in your applications:

- [OpenAPI Updates in .NET 9][aspnetcore-openapi-stream-1]
- [OpenAPI Updates in .NET 9 (Part 2)][aspnetcore-openapi-stream-2]

The documentation for the package for ASP.NET Core 9 can be found in [Microsoft Learn][aspnetcore-openapi].

## Summary

TODO

[aspnetcore-9]: https://learn.microsoft.com/aspnet/core/release-notes/aspnetcore-9.0 "What's new in ASP.NET Core 9.0"
[aspnetcore-endpoints]: https://learn.microsoft.com/aspnet/core/fundamentals/routing "Routing in ASP.NET Core"
[aspnetcore-openapi]: https://learn.microsoft.com/aspnet/core/fundamentals/openapi/aspnetcore-openapi?view=aspnetcore-9.0 "Work with OpenAPI documents on Microsoft Learn"
[aspnetcore-openapi-stream-1]: https://www.youtube.com/watch/XoMese9g8WQ "ASP.NET Community Standup - OpenAPI Updates in .NET 9 on YouTube"
[aspnetcore-openapi-stream-2]: https://www.youtube.com/watch/keK69Y5HqvY "ASP.NET Community Standup - OpenAPI Updates in .NET 9 (Part 2) on YouTube"
[aspnetcore-openapi-xml]: https://github.com/dotnet/aspnetcore/issues/39927#issuecomment-2233634912 "Support XML-based OpenAPI docs for minimal APIs"
[example-aspnetcore]: https://github.com/martincostello/aspnetcore-openapi/blob/06b3aff0e5023cce8a5c8599507b4d974aedf37b/src/TodoApp/OpenApi/AspNetCore/AspNetCoreOpenApiEndpoints.cs
[example-nswag]: https://github.com/martincostello/aspnetcore-openapi/blob/06b3aff0e5023cce8a5c8599507b4d974aedf37b/src/TodoApp/OpenApi/NSwag/NSwagOpenApiEndpoints.cs
[example-swashbuckle]: https://github.com/martincostello/aspnetcore-openapi/blob/06b3aff0e5023cce8a5c8599507b4d974aedf37b/src/TodoApp/OpenApi/Swashbuckle/SwashbuckleOpenApiEndpoints.cs
[linting]: https://learn.microsoft.com/aspnet/core/fundamentals/openapi/aspnetcore-openapi?view=aspnetcore-9.0#lint-generated-openapi-documents-with-spectral "Lint generated OpenAPI documents with Spectral"
[microsoft-aspnetcore-openapi]: https://www.nuget.org/packages/Microsoft.AspNetCore.OpenApi "The Microsoft.AspNetCore.OpenApi package on NuGet.org"
[m-e-apidescription-server]: https://www.nuget.org/packages/Microsoft.Extensions.ApiDescription.Server/ "The Microsoft.Extensions.ApiDescription.Server package on NuGet.org"
[microsoft-openapi]: https://github.com/microsoft/OpenAPI.NET "The OpenAPI.NET repository on GitHub"
[native-aot]: https://learn.microsoft.com/dotnet/core/deploying/native-aot "Native AOT deployment"
[nswag]: https://github.com/RicoSuter/NSwag "The NSwag repository on GitHub"
[openapi]: https://swagger.io/docs/specification/about/ "What Is OpenAPI?"
[openapi-comparisons]: https://github.com/martincostello/aspnetcore-openapi "A GitHub repository comparing OpenAPI implementations for ASP.NET Core"
[openapi-extensions]: https://github.com/martincostello/openapi-extensions "The OpenAPI Extensions repository on GitHub"
[openapi-specification]: https://swagger.io/specification/ "The OpenAPI specification"
[safia-abdalla]: https://github.com/captainsafia "@captainsafia on GitHub"
[spectral]: https://github.com/stoplightio/spectral "The Spectral repository on GitHub"
[swagger-ui]: https://github.com/swagger-api/swagger-ui "The Swagger UI repository on GitHub"
[swashbuckle]: https://github.com/domaindrivendev/Swashbuckle.AspNetCore "The Swashbuckle.AspNetCore repository on GitHub"
[swashbuckle-maintainers]: https://github.com/domaindrivendev/Swashbuckle.AspNetCore/discussions/2778 "Swashbuckle.AspNetCore maintainers announcement"
[swashbuckle-ui]: https://www.nuget.org/packages/Swashbuckle.AspNetCore.SwaggerUI "The Swashbuckle.AspNetCore.SwaggerUI package on NuGet.org"
