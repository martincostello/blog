---
title: Using the .NET JSON Source Generator with ASP.NET Core Minimal APIs
date: 2021-11-28
tags: aspnet,aspnetcore,dotnet,json,minimal apis,source generators
layout: post
description: "How to use the new .NET 6 JSON source generator in an application using ASP.NET Core Minimal APIs."
---

I've recently completed upgrading a bunch of personal and work applications to
ASP.NET Core 6, and now that the dust has finally settled on those efforts, I
thought I'd look into a new feature of .NET 6 that I hadn't tried out yet - [JSON source generators][1].

If you haven't come across them before, [C# source generators][2] are a way to
write some code that can generate more code during compilation. It's a form of
[metaprogramming][3].

One of the benefits of the new JSON source generator for the System.Text.Json
serializer is that it is more performant that the APIs introduced as part of
.NET 5. This is because the serializer is able to leverage code that is compiled
ahead-of-time (the source generator part) to serialize and deserialize objects
to and from JSON without using reflection (which is relatively slow).

It sounds like that could give applications a performance boost at runtime, but
how can we use the new JSON source generator with ASP.NET Core [Minimal APIs][4]?

READMORE

Out-of-the box, ASP.NET Core Minimal APIs do not currently support using the JSON
source generator and just use the `JsonSerializer.SerializeAsync()` methods
under-the-hood ([this code][5], which calls through to [this code][6]). For us
to leverage the JSON source generator we need to be able to use one of the new
`SerializeAsync()` overloads added as part of .NET 6 that take either a
[`JsonSerializerContext`][7] or a [`JsonTypeInfo<T>`][8] parameter somehow. How
can we achieve that while still keeping our HTTP endpoint code minimal?

One approach we can use to do this is to use the [extensibility hook][9] built
into the `Results` class - the [`Results.Extensions`][10] property and the
`IResultExtensions` interface. By adding a custom extension method for the
`IResultExtensions` interface, we can add a new `Json()` method we can use in
our Minimal API endpoints which we can use to leverage the JSON source generator.

Here's a simplified example of this for `JsonTypeInfo<T>`:

```
using System.Text.Json.Serialization;
using System.Text.Json.Serialization.Metadata;
using Microsoft.AspNetCore.Http.Result;

namespace Microsoft.AspNetCore.Http;

public static class ResultExtensions
{
    public static IResult Json<T>(
        this IResultExtensions extensions,
        T value,
        JsonTypeInfo<T> jsonTypeInfo,
        string? contentType = null,
        int? statusCode = null)
    {
        return new JsonResult<T>
        {
            ContentType = contentType,
            JsonTypeInfo = jsonTypeInfo,
            StatusCode = statusCode,
            Value = value,
        };
    }
}
```

We extend the `IResultExtensions` and pass through the `JsonTypeInfo<T>` along
with the data we want to serialize as JSON and assign them to a `JsonResult<T>`
instance, which is returned to the caller. This is a custom implementation of the
[`IResult`][11] interface, which is where we'll put the code to actually do the
JSON serialization.

Using ASP.NET Core 6's [own implementation][12] as inspiration, we can then
write some extension methods for the [`HttpResponse`][13] class which can then
pass this information along to the [`JsonSerializer`][14] class to serialize
our objects to JSON using our source-generated implementation.

Below are some snippets from the relevant code.

```
// JsonResult<T>
Task IResult.ExecuteAsync(HttpContext httpContext)
{
    if (StatusCode is int statusCode)
    {
        httpContext.Response.StatusCode = statusCode;
    }

    return httpContext.Response.WriteAsJsonAsync(Value, JsonTypeInfo, ContentType);
}

// HttpResponseJsonExtensions
public static Task WriteAsJsonAsync<T>(
    this HttpResponse response,
    T value,
    JsonTypeInfo<T> jsonTypeInfo,
    string? contentType,
    CancellationToken cancellationToken = default)
{
    response.ContentType = contentType ?? "application/json; charset=utf-8";
    return JsonSerializer.SerializeAsync(response.Body, value, jsonTypeInfo, cancellationToken);
}
```

With these extensions available, we can then modify our Minimal API endpoints to
use our new `Json()` method with our source-generated code.

```
var planets = new Planet[]
{
    new() { Name = "Mercury" },
    new() { Name = "Venus" },
    new() { Name = "Earth" },
    new() { Name = "Mars" }
};

// Create an instance of our custom JsonSerializerContext using the JSON
// serializer settings we want to use with it, such as to be in camelCase.
var context = new StellarJsonSerializerContext(new(JsonSerializerDefaults.Web));

// Use Results.Extensions.Json() with our serializer context
app.MapGet("/planets", () => Results.Extensions.Json(planets, context.PlanetArray));

public class Planet
{
    public string Name { get; set; }
}

// Our custom JSON serializer context that generates code to serialize
// arrays of planets so that we can use it with our HTTP endpoint.
[JsonSerializable(typeof(Planet[]))]
public partial class StellarJsonSerializerContext : JsonSerializerContext
{
}
```

We could also add other extension methods that we can use that rely on a
`JsonSerializerContext` being registered with the service provider used for
[dependency injection][15] to simplify the endpoint code even further. Then the
context isn't even referenced in the endpoints' code, and the only difference to
using the built-in `Json()` method is the `Extensions` part, like this.

```
app.MapGet("/planets", () => Results.Extensions.Json(planets));
```

A complete sample application containing all the code referenced in this blog
post can be found in GitHub here: [https://github.com/martincostello/MinimalApisWithJsonSourceGenerator][16]

I hope you find it useful - happy coding!

[1]: https://devblogs.microsoft.com/dotnet/try-the-new-system-text-json-source-generator/
[2]: https://devblogs.microsoft.com/dotnet/introducing-c-source-generators/
[3]: https://en.wikipedia.org/wiki/Metaprogramming
[4]: https://docs.microsoft.com/en-us/aspnet/core/fundamentals/minimal-apis
[5]: https://github.com/dotnet/aspnetcore/blob/ae1a6cbe225b99c0bf38b7e31bf60cb653b73a52/src/Http/Http.Results/src/JsonResult.cs#L57
[6]: https://github.com/dotnet/aspnetcore/blob/ae1a6cbe225b99c0bf38b7e31bf60cb653b73a52/src/Http/Http.Extensions/src/HttpResponseJsonExtensions.cs#L91
[7]: https://docs.microsoft.com/en-us/dotnet/api/system.text.json.jsonserializer.serializeasync?view=net-6.0#System_Text_Json_JsonSerializer_SerializeAsync_System_IO_Stream_System_Object_System_Type_System_Text_Json_Serialization_JsonSerializerContext_System_Threading_CancellationToken_
[8]: https://docs.microsoft.com/en-us/dotnet/api/system.text.json.jsonserializer.serializeasync?view=net-6.0#System_Text_Json_JsonSerializer_SerializeAsync__1_System_IO_Stream___0_System_Text_Json_Serialization_Metadata_JsonTypeInfo___0__System_Threading_CancellationToken_
[9]: https://docs.microsoft.com/en-us/aspnet/core/fundamentals/minimal-apis?view=aspnetcore-6.0#customizing-results
[10]: https://docs.microsoft.com/en-us/dotnet/api/microsoft.aspnetcore.http.results.extensions?view=aspnetcore-6.0#Microsoft_AspNetCore_Http_Results_Extensions
[11]: https://docs.microsoft.com/en-us/dotnet/api/microsoft.aspnetcore.http.iresult?view=aspnetcore-6.0
[12]: https://github.com/dotnet/aspnetcore/blob/ae1a6cbe225b99c0bf38b7e31bf60cb653b73a52/src/Http/Http.Results/src/JsonResult.cs#L47-L58
[13]: https://docs.microsoft.com/en-us/dotnet/api/microsoft.aspnetcore.http.httpresponse?view=aspnetcore-6.0
[14]: https://docs.microsoft.com/en-us/dotnet/api/system.text.json.jsonserializer?view=net-6.0
[15]: https://docs.microsoft.com/en-us/aspnet/core/fundamentals/dependency-injection?view=aspnetcore-6.0
[16]: https://github.com/martincostello/MinimalApisWithJsonSourceGenerator
