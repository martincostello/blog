---
title: "What's New for OpenAPI with .NET 9"
date: 2024-09-09
tags: dotnet,openapi,swagger,swashbuckle
layout: bloglayout
description: "A look at the new Microsoft.AspNetCore.OpenApi package in .NET 9 and comparing it to NSwag and Swashbuckle.AspNetCore."
image: "https://cdn.martincostello.com/blog_openapi.png"
---

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_openapi.png" alt="The OpenAPI logo" title="The OpenAPI logo" height="272px" width="899px">

Developers in the .NET ecosystem have been writing APIs with ASP.NET and ASP.NET Core for years, and
[OpenAPI][openapi] has been a popular choice for documenting those APIs.
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

While Swashbuckle has had a bit of a resurgence in 2024 with the [announcement of new maintainers for the project][swashbuckle-maintainers],
of which I'm one 👋, and now has first-class support for .NET 8, it is still an open source project that is provided
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
to be updating, rather than also needing to fully implement the specification itself. The generation of the JSON schemas for
the models is built on top of the [new JSON schema support][json-schema-exporter] in .NET 9, which is exposed by the new
`JsonSchemaExporter` class.

OpenAPI support is added at the [endpoint][aspnetcore-endpoints] level (think `MapGet()` and similar methods). This allow the
the OpenAPI document can be coupled into other mechanisms in ASP.NET Core, such as authorization, caching, and more.

As noted earlier, it is also fully compatible with native AoT, allowing you to generate OpenAPI documents for your ASP.NET Core
applications at runtime even when compiled to native code, such as when running in a container, if you want to expose your
API documentation to your users in your deployed environment.

To add the minimal level of support for generating an OpenAPI document, you could add the following code to your ASP.NET Core
application after adding a reference to the Microsoft.AspNetCore.OpenApi NuGet package:

<pre class="highlight plaintext">
<code>var builder = WebApplication.CreateBuilder();

// Add services for generating OpenAPI documents
builder.Services.AddOpenApi();

var app = builder.Build();

// Add the endpoint to get the OpenAPI document
app.MapOpenApi();

// Your API endpoints
app.MapGet("/", () => "Hello world!");

app.Run();</code>
</pre>

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

<pre class="highlight plaintext">
<code>// Add a custom service to the DI container
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
}</code>
</pre>

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

<pre class="highlight plaintext">
<code>app.MapOpenApiYaml();</code>
</pre>

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

<pre class="highlight plaintext">
<code>public static IServiceCollection AddAspNetCoreOpenApi(this IServiceCollection services)
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
}</code>
</pre>

[Code][example-aspnetcore]

### NSwag

<pre class="highlight plaintext">
<code>public static IServiceCollection AddNSwagOpenApi(this IServiceCollection services)
{
    services.AddOpenApiDocument(options =>
    {
        options.Title = "Todo API";
        options.Description = "An API for managing Todo items.";
        options.Version = "v1";

        options.OperationProcessors.Add(new AddExamplesProcessor());
    });

    return services;
}</code>
</pre>

[Code][example-nswag]

### Swashbuckle

<pre class="highlight plaintext">
<code>public static IServiceCollection AddSwashbuckleOpenApi(this IServiceCollection services)
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
}</code>
</pre>

[Code][example-swashbuckle]

## Performance

The last thing I thought I'd touch on in this blog post is performance. After I'd created the repository comparing the three
implementations, I figured it would be interesting to benchmark them to compare how they perform when generating an OpenAPI document.

### Preliminary Results with .NET 9 Preview 7

After a detour off into setting up a continuous benchmarking process ([read about it here][continuous-benchmarks]),
I set up some benchmarks for each library with [BenchmarkDotNet][benchmarkdotnet] to compare the performance. When I first set
them up I was targeting the official Preview 7 release of .NET 9, and at a very high-level, these were the results I got:

<pre class="highlight plaintext">
<code>BenchmarkDotNet v0.14.0, Ubuntu 22.04.4 LTS (Jammy Jellyfish)
AMD EPYC 7763, 1 CPU, 4 logical and 2 physical cores
.NET SDK 9.0.100-preview.7.24406.8
  [Host]   : .NET 9.0.0 (9.0.24.40507), X64 RyuJIT AVX2
  ShortRun : .NET 9.0.0 (9.0.24.40507), X64 RyuJIT AVX2

Job=ShortRun  IterationCount=3  LaunchCount=1
WarmupCount=3

| Method      | Mean      | Error     | StdDev    | Gen0     | Gen1     | Gen2     | Allocated |
|------------ |----------:|----------:|----------:|---------:|---------:|---------:|----------:|
| AspNetCore  | 10.988 ms | 13.319 ms | 0.7301 ms | 171.8750 | 140.6250 | 125.0000 |   6.02 MB |
| NSwag       | 12.269 ms |  2.276 ms | 0.1247 ms |  15.6250 |        - |        - |   1.55 MB |
| Swashbuckle |  7.989 ms |  6.878 ms | 0.3770 ms |  15.6250 |        - |        - |    1.5 MB |</code>
</pre>

[Commit][benchmark-commit-preview7]

As you can see from the data, the new OpenAPI package is roughly second along with NSwag in terms of performance, with Swashbuckle
ahead by a few milliseconds. However the new ASP.NET Core OpenAPI is _way_ behind in terms of memory usage, using nearly 4 times as
much as the other two libraries. You can also see from the graphs below from many runs over time with the preview 7 that there is
a lot of variance in the OpenAPI package's performance, compared to the other two libraries which are much more stable.

<!-- markdownlint-disable-file MD033 -->
<div class="container mx-1">
  <div class="row">
    <div class="col">
      <img class="img-fluid" src="https://cdn.martincostello.com/blog_openapi-aspnetcore-preview7.png" alt="ASP.NET Core results for .NET 9 preview 7" title="ASP.NET Core results for .NET 9 preview 7" >
    </div>
    <div class="col">
      <img class="img-fluid" src="https://cdn.martincostello.com/blog_openapi-nswag-preview7.png" alt="NSwag results for .NET 9 preview 7" title="NSwag results for .NET 9 preview 7">
    </div>
    <div class="col">
      <img class="img-fluid" src="https://cdn.martincostello.com/blog_openapi-swashbuckle-preview7.png" alt="Swashbuckle results for .NET 9 preview 7" title="Swashbuckle results for .NET 9 preview 7">
    </div>
  </div>
</div>

Not particularly great, but there's actually two interesting caveats to these benchmarks.

The first is that [there is a bug][dotnet-aspnetcore-56990] in ASP.NET Core 9 Preview 7 that caused the OpenAPI document schemas to
not be stable between generations - this was leading to a lot of unncessary work being done, and was causing a memory leak that
eventually caused OpenAPI generation to stop working completely. Because of this issue, I had to cap the number of iterations the
benchmarks ran as a short run via `[ShortRunJob]`, otherwise the benchmarks would grind to a halt. This is also the cause of the
variance in the allocation numbers (the red line at the top of the first graph).

The second caveat is that, by default, NSwag caches the OpenAPI document it generates, so out-of-the-box it will only ever generate
the OpenAPI document once. For the sake of comparison, I [disabled the caching][disabled-caching] in NSwag so that the document was
generated in full on each request. We _could_ level the playing field in the opposite direction by caching all three, but that's not
interesting for a performance comparison/test as we'd effectively just be benchmarking the caching 😄.

### Gotta Go Fast 🦔💨

With some data to hand, I then took a look into what exactly the code was doing to see if there was anything obvious that could be
fixed or improved to speed things up. What was invaluable in this process was the [EventPipe Profiler][benchmarkdotnet-profiler]
that can be enabled in BenchmarkDotNet to capture a flame graph of the code being executed. Using [speedscope.app][speedscope] I
was able to visualise the code paths that were being executed and see where the time was being spent. With this information, I was
able to identify three different places where the OpenAPI generation was doing unnecessary work and causing the performance issues.

#### Dictionary Lookups 🕵️📖

The first thing I found was that the code seemed to be spending a lot of time in the `Enumerable.All()` method. Digging into this
further, I noticed that `IDictionary<K, V>.Contains()` was being used in a number of places in the code along with the indexer.
This is a known performance trap in .NET, with this pattern leading to a double look-up, which can be avoided by instead using the
`TryGetValue()` method.

In fact there's even a .NET analysis rule that covers this scenario: [CA1854][ca1854]. It turns out
[there's a bug][dotnet-roslyn-analyzers-7369] in this analyser that doesn't catch certain patterns of usage, which is why it wasn't
caught previously.

Changing the code to use `TryGetValue()` instead was an easy enough change to make, but that didn't answer the question of why so
much time was being spent in `All()` in the first place. The reason for this turned out to be due to the way the OpenAPI library
was implementing [`IEqualityComparer<T>`][iequalitycomparer] for the various types used to generate the OpenAPI document.

Some custom equality comparers are implemented which are used to help test whether different OpenAPI schema "shapes" are equal to
each other or not. These objects in some cases contain dozens of properties, some of which are themselves dictionaries or arrays,
which can create a large object graph to traverse to compute the equality of.

With some reordering to how the properties are computed based on expense/likelihood of being different, a lot of the cost of these
comparisons can be avoided and make things much faster in the majority of cases.

I opened [a pull request][dotnet-aspnetcore-57208] to address both of these items, which once merged caused all of the identified
method calls to drop out of the hot path for the profiler traces in the benchmarks 🔥.

#### Too Many Transformers 🤖

After the fix for the unstable schemas and the above changes, I took another look at the traces from my benchmark runs and
spotted one other anomaly from the data. Looking at the data, I noticed that [transformers were being created too often][dotnet-aspnetcore-57211].

This was due to an issue with the lifetime and disposal of transformers, meaning that they were being created once per _schema_,
rather than once per generation of the OpenAPI _document_. This then had not only the overhead of the additional work, but also
an impact to memory usage and garbage collection.

### Latest Results with .NET 9 RC.1

After changes for the above issues were merged, I re-ran the benchmarks against the latest daily build of .NET 9 from their CI,
as at the time of writing, .NET 9 RC.1 isn't officially available yet. I've [written about using daily builds before][daily-builds], so
check out that post if you're interested.

With the latest version of the .NET SDK from the .NET 9 CI (`9.0.100-rc.1.24452.12` at the time of writing) things are noticeably
improved compared to preview 7:

<pre class="highlight plaintext">
<code>BenchmarkDotNet v0.14.0, Ubuntu 22.04.4 LTS (Jammy Jellyfish)
AMD EPYC 7763, 1 CPU, 4 logical and 2 physical cores
.NET SDK 9.0.100-rc.1.24452.12
  [Host]     : .NET 9.0.0 (9.0.24.43107), X64 RyuJIT AVX2
  DefaultJob : .NET 9.0.0 (9.0.24.43107), X64 RyuJIT AVX2

| Method      | Mean       | Error    | StdDev    | Median     | Gen0    | Allocated  |
|------------ |-----------:|---------:|----------:|-----------:|--------:|-----------:|
| AspNetCore  |   981.9 us | 15.94 us |  30.34 us |   975.3 us |       - |  326.64 KB |
| NSwag       | 4,570.8 us | 60.82 us |  53.92 us | 4,556.4 us | 15.6250 | 1588.43 KB |
| Swashbuckle | 2,768.2 us | 52.00 us | 124.58 us | 2,721.2 us | 15.6250 |    1527 KB |</code>
</pre>

[Commit][benchmark-commit-rc1]

As you can see compared to the previous results, the OpenAPI package is now the fastest of the three libraries.

The new ASP.NET Core package beats both NSwag and Swashbuckle by a significant margin, both in terms of time _and_ memory. ⚡

In fact it's almost **~2.8x** faster, and **~4.6x** less memory hungry that the nearest competitor.

Compared to itself from preview 7, it's now **~11x** faster and allocates **~18x** less memory. That's a huge improvement! 🚀

<div class="container mx-1">
  <div class="row">
    <div class="col">
      <img class="img-fluid" src="https://cdn.martincostello.com/blog_openapi-aspnetcore-rc1.png" alt="ASP.NET Core results for .NET 9 preview 7" title="ASP.NET Core results for .NET 9 preview 7" >
    </div>
    <div class="col">
      <img class="img-fluid" src="https://cdn.martincostello.com/blog_openapi-nswag-rc1.png" alt="NSwag results for .NET 9 preview 7" title="NSwag results for .NET 9 preview 7">
    </div>
    <div class="col">
      <img class="img-fluid" src="https://cdn.martincostello.com/blog_openapi-swashbuckle-rc1.png" alt="Swashbuckle results for .NET 9 preview 7" title="Swashbuckle results for .NET 9 preview 7">
    </div>
  </div>
</div>

The caveats to note here:

- `[ShortRunJob]` is no longer used, so the benchmarks run more iterations and are thus more accurate. This is why
  the error bars are much smaller in the second series of graphs.
- _All_ improvements between .NET 9 preview 7 and release candidate 1 are included, not just the fixes for OpenAPI.
  This is most apparent from the major step down on the graph for all three libraries a few points in from the left.
  This is where the benchmark project switches from using preview 7 to the daily RC1 builds.

As ever, performance is relative to the environment used and your numbers might vary. However with a relatively stable
environment (GitHub Actions' Ubuntu runners in this case), the graphs show consistent performance across multiple runs
and a clear improvement as newer versions of .NET 9 are used. The useful data here is in the trends, not the absolute values.

## Further Reading

For more information on the new features in the Microsoft.AspNetCore.OpenApi package, check out these ASP.NET Community Standup
streams on YouTube. Here [Safia Abdalla][safia-abdalla], the engineer behind this new functionality, explains the new features
in the package and how to use them in your applications:

- [OpenAPI Updates in .NET 9][aspnetcore-openapi-stream-1]
- [OpenAPI Updates in .NET 9 (Part 2)][aspnetcore-openapi-stream-2]

The documentation for the package for ASP.NET Core 9 can be found in [Microsoft Learn][aspnetcore-openapi].

## Summary

All in all, the new ASP.NET Core OpenAPI package is a great addition to the ASP.NET Core ecosystem. It provides a modern and
performant way to generate OpenAPI documents for your ASP.NET Core applications to cover the core use cases that developers need.

While it may not yet be as feature-rich as existing libraries such as NSwag or Swashbuckle, it's better ability to keep up with
the change of pace to ASP.NET Core now and in the future, such as support for native AoT, give it a strong foundation to build
on going forwards, such as for future support for OpenAPI 3.1.

Developers don't need to switch from their existing libraries to the new OpenAPI package if they're happy with their current
implementation - the only compelling reason to switch is if you want to generate OpenAPI documents in a native AoT deployment.
For those who do wish to switch (I have for a number of my apps), the migration is easiest for users of Swashbuckle.AspNetCore due
to both libraries being built on top of the same OpenAPI.NET foundation.

If you've not added OpenAPI documentation to an API before and are writing a new ASP.NET Core 9+ application, I'd recommend giving
the library a try to see how it fits your needs. It's a great way to get started with OpenAPI documentation for your APIs!

[aspnetcore-9]: https://learn.microsoft.com/aspnet/core/release-notes/aspnetcore-9.0 "What's new in ASP.NET Core 9.0"
[aspnetcore-endpoints]: https://learn.microsoft.com/aspnet/core/fundamentals/routing "Routing in ASP.NET Core"
[aspnetcore-openapi]: https://learn.microsoft.com/aspnet/core/fundamentals/openapi/aspnetcore-openapi?view=aspnetcore-9.0 "Work with OpenAPI documents on Microsoft Learn"
[aspnetcore-openapi-stream-1]: https://www.youtube.com/watch/XoMese9g8WQ "ASP.NET Community Standup - OpenAPI Updates in .NET 9 on YouTube"
[aspnetcore-openapi-stream-2]: https://www.youtube.com/watch/keK69Y5HqvY "ASP.NET Community Standup - OpenAPI Updates in .NET 9 (Part 2) on YouTube"
[aspnetcore-openapi-xml]: https://github.com/dotnet/aspnetcore/issues/39927#issuecomment-2233634912 "Support XML-based OpenAPI docs for minimal APIs"
[benchmarkdotnet]: https://github.com/dotnet/BenchmarkDotNet "The BenchmarkDotNet repository on GitHub"
[benchmarkdotnet-profiler]: https://benchmarkdotnet.org/articles/features/event-pipe-profiler.html "EventPipeProfiler"
[benchmark-commit-preview7]: https://github.com/martincostello/aspnetcore-openapi/commit/fd5d79a12deeeda3abc10b61a80f2568bd38b381
[benchmark-commit-rc1]: https://github.com/martincostello/aspnetcore-openapi/commit/6a09d0422eeeabe38cc4ea7655af04d5d7209d11
[ca1854]: https://learn.microsoft.com/dotnet/fundamentals/code-analysis/quality-rules/CA1854 "Prefer the IDictionary.TryGetValue(TKey, out TValue) method"
[continuous-benchmarks]: https://blog.martincostello.com/continuous-benchmarks-on-a-budget/
[daily-builds]: https://blog.martincostello.com/upgrading-to-dotnet-8-part-5-preview-7-and-rc-1-2/ "Daily Build Testing"
[disabled-caching]: https://github.com/martincostello/aspnetcore-openapi/blob/fd5d79a12deeeda3abc10b61a80f2568bd38b381/src/TodoApp/OpenApi/NSwag/NSwagOpenApiEndpoints.cs#L94-L97
[dotnet-aspnetcore-56990]: https://github.com/dotnet/aspnetcore/issues/56990 "OpenAPI schemas are not stable between generations"
[dotnet-aspnetcore-57208]: https://github.com/dotnet/aspnetcore/pull/57208 "Use TryGetValue for dictionary lookups in OpenAPI comparers"
[dotnet-aspnetcore-57211]: https://github.com/dotnet/aspnetcore/pull/57211 "OpenAPI activates transformers too many times"
[dotnet-roslyn-analyzers-7369]: https://github.com/dotnet/roslyn-analyzers/issues/7369 "CA1854 isn't catching cases that aren't directly part of an if statement"
[example-aspnetcore]: https://github.com/martincostello/aspnetcore-openapi/blob/06b3aff0e5023cce8a5c8599507b4d974aedf37b/src/TodoApp/OpenApi/AspNetCore/AspNetCoreOpenApiEndpoints.cs
[example-nswag]: https://github.com/martincostello/aspnetcore-openapi/blob/06b3aff0e5023cce8a5c8599507b4d974aedf37b/src/TodoApp/OpenApi/NSwag/NSwagOpenApiEndpoints.cs
[example-swashbuckle]: https://github.com/martincostello/aspnetcore-openapi/blob/06b3aff0e5023cce8a5c8599507b4d974aedf37b/src/TodoApp/OpenApi/Swashbuckle/SwashbuckleOpenApiEndpoints.cs
[iequalitycomparer]: https://learn.microsoft.com/dotnet/api/system.collections.generic.iequalitycomparer-1 "IEqualityComparer<T> Interface"
[json-schema-exporter]: https://github.com/dotnet/core/blob/main/release-notes/9.0/preview/preview6/libraries.md#jsonschemaexporter "JsonSchemaExporter"
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
[speedscope]: https://www.speedscope.app/ "speedscope"
[swagger-ui]: https://github.com/swagger-api/swagger-ui "The Swagger UI repository on GitHub"
[swashbuckle]: https://github.com/domaindrivendev/Swashbuckle.AspNetCore "The Swashbuckle.AspNetCore repository on GitHub"
[swashbuckle-maintainers]: https://github.com/domaindrivendev/Swashbuckle.AspNetCore/discussions/2778 "Swashbuckle.AspNetCore maintainers announcement"
[swashbuckle-ui]: https://www.nuget.org/packages/Swashbuckle.AspNetCore.SwaggerUI "The Swashbuckle.AspNetCore.SwaggerUI package on NuGet.org"
