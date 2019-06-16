---
title: Using Refit with the new System.Text.Json APIs
date: 2019-06-16
tags: aspnetcore,dotnet,refit
layout: bloglayout
description: "Using Refit with the new System.Text.Json APIs in .NET Core 3.0 to boost performance"
---

I'm a big fan of the [_Refit_](https://github.com/reactiveui/refit "Refit on GitHub.com") library for calling HTTP APIs from my .NET applications.

It uses code generation to let you do simple HTTP calls using interfaces and uses [JSON.NET](https://www.newtonsoft.com/json "JSON.NET website") under-the-hood to handle serializing and deserializing JSON.

For example, to get a repository from the GitHub API you could define these types:

```
public class Organization
{
    [JsonProperty("login")]
    public string Login { get; set; }

    [JsonProperty("id")]
    public long Id { get; set; }

    // ... other properties
}

[Headers("Accept: application/vnd.github.v3+json", "User-Agent: My-App/1.0.0")]
public interface IGitHub
{
    [Get("/orgs/{organization}")]
    Task<Organization> GetOrganizationAsync(string organization);
}
```

Then you could call the API like this:

```
var client = RestService.For<IGitHub>("https://api.github.com");
var org = await client.GetOrganizationAsync("dotnet");
```

This week [.NET Core 3.0 preview 6](https://devblogs.microsoft.com/dotnet/announcing-net-core-3-0-preview-6/ "Announcing .NET Core 3.0 Preview 6") was released, and with that the [new System.Text.Json APIs](https://devblogs.microsoft.com/dotnet/try-the-new-system-text-json-apis/ "Try the new System.Text.Json APIs"). These new APIs are designed to be more performant and do less allocations that JSON.NET, so should bring performance benefits to applications that use them.

So what should you do if you want to use the new System.Text.Json APIs with Refit?

READMORE

Refit has some extension points to let you change how things work, including JSON (de)serialization. This means we can just provide our own `IContentSerializer` implementation to the `RefitSettings` class to replace JSON.NET with the new JSON APIs.

Here's the equivalent code to the above to create an `IGitHub` instance with the new serializer. The complete code for the `SystemTextJsonContentSerializer` class is at the bottom of this post.

```
var options = new JsonSerializerOptions()
{
    PropertyNamingPolicy = JsonNamingPolicy.CamelCase,
    WriteIndented = true,
};

var settings = new RefitSettings()
{
    ContentSerializer = new SystemTextJsonContentSerializer(options)
};

var client = RestService.For<IGitHub>("https://api.github.com", settings);
```

You'll also need to update your objects if you use attributes to control the serialization of the property names, for example the `Organization` object defined above becomes the following, with `[JsonProperty("...")]` replaced with `[JsonPropertyName("...")]` from the `System.Text.Json.Serialization` namespace.

```
using System.Text.Json.Serialization;

public class Organization
{
    [JsonPropertyName("login")]
    public string Login { get; set; }

    [JsonPropertyName("id")]
    public long Id { get; set; }

    // ... other properties
}
```

I decided to put together some simple benchmarks with [BenchmarkDotNet](https://github.com/dotnet/BenchmarkDotNet "BenchmarkDotNet on GitHub.com") to compare the two implementations. If you want to run them yourself you can find the repo here: [https://github.com/martincostello/Refit-Json-Benchmarks](https://github.com/martincostello/Refit-Json-Benchmarks "Refit-Json-Benchmarks on GitHub.com")

There's three benchmarks that try out reading an object, reading a collection and writing an object using both JSON.NET and System.Text.Json using a stubbed-out `HttpClient` to the GitHub API.

## _So how much faster is it?_

The results are fairly impressive on my laptop running Windows 10 ([full results](https://github.com/martincostello/Refit-Json-Benchmarks#results "Benchmark results")):

<table>
  <thead>
    <tr>
      <td><strong>Benchmark</strong></td>
      <td><strong>Mean</strong></td>
      <td><strong>Ratio</strong></td>
      <td><strong>Allocated</strong></td>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Read_Collection_NewtonsoftJson</td>
      <td>2.366 ms</td>
      <td>1.00</td>
      <td>297.95 KB</td>
    </tr>
    <tr>
      <td>Read_Collection_SystemTextJson</td>
      <td>1.404 ms</td>
      <td>0.55</td>
      <td>270.18 KB</td>
    </tr>
    <tr>
      <td>Read_Object_NewtonsoftJson</td>
      <td>219.60 μs</td>
      <td>1.00</td>
      <td>14.15 KB</td>
    </tr>
    <tr>
      <td>Read_Object_SystemTextJson</td>
      <td>29.42 μs</td>
      <td>0.13</td>
      <td>6.49 KB</td>
    </tr>
    <tr>
      <td>Write_Object_NewtonsoftJson</td>
      <td>22.79 μs</td>
      <td>1.00</td>
      <td>8.09 KB</td>
    </tr>
    <tr>
      <td>Write_Object_SystemTextJson</td>
      <td>16.23 μs</td>
      <td>0.71</td>
      <td>6.13 KB</td>
    </tr>
  </tbody>
</table>

<hr/>

For these specific example requests and responses, for reading an object **the mean time per operation is reduced by 87%** and the amount of **memory allocated reduced by 55%**!

If you use Refit heavily in an existing .NET Core application to consume JSON it looks like there's a lot of performance gain to be had by switching from JSON.NET to the new System.Text.Json APIs in .NET Core 3.0!

## Links

  * [Refit](https://github.com/reactiveui/refit)
  * [JSON.NET](https://www.newtonsoft.com/json)
  * [_Try the new System.Text.Json APIs_](https://devblogs.microsoft.com/dotnet/try-the-new-system-text-json-apis/)
  * [_Announcing .NET Core 3.0 Preview 6_](https://devblogs.microsoft.com/dotnet/announcing-net-core-3-0-preview-6/)
  * [Refit Benchmarks with System.Text.Json](https://github.com/martincostello/Refit-Json-Benchmarks)

## `SystemTextJsonContentSerializer` Code

```
using System;
using System.IO;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json.Serialization;
using System.Threading.Tasks;
using Refit;

namespace RefitWithSystemTextJson
{
    public sealed class SystemTextJsonContentSerializer : IContentSerializer
    {
        private static readonly MediaTypeHeaderValue _jsonMediaType =
            new MediaTypeHeaderValue("application/json") { CharSet = Encoding.UTF8.WebName };

        public SystemTextJsonContentSerializer(JsonSerializerOptions serializerOptions)
        {
            SerializerOptions = serializerOptions;
        }

        private JsonSerializerOptions SerializerOptions { get; }

        public async Task<T> DeserializeAsync<T>(HttpContent content)
        {
            using var utf8Json = await content.ReadAsStreamAsync();
            return await JsonSerializer.ReadAsync<T>(utf8Json, SerializerOptions);
        }

        public async Task<HttpContent> SerializeAsync<T>(T item)
        {
            var stream = new MemoryStream();

            try
            {
                await JsonSerializer.WriteAsync(item, stream, SerializerOptions);
                await stream.FlushAsync();

                var content = new StreamContent(stream);

                content.Headers.ContentType = _jsonMediaType;

                return content;
            }
            catch (Exception)
            {
                await stream.DisposeAsync();
                throw;
            }
        }
    }
}
```
