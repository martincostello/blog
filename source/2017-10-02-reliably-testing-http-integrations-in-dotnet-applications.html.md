---
title: Reliably Testing HTTP Integrations in a .NET Application
date: 2017-10-02
tags: testing,httpclient
layout: bloglayout
description: "Reliably testing your HTTP API integrations in .NET with JustEat.HttpClientInterception to intercept your HTTP calls to use custom HTTP responses."
---

> _This blog post was originally published by me on the [Just Eat Tech blog][original-post]._

## Introduction

Testing HTTP dependencies in modern web applications is a common problem, but it’s also something that can
create difficulty for authoring reliable tests. Today, we’re open-sourcing a library to help reduce the friction
many developers have with this common requirement: JustEat.HttpClientInterception. You can find the repository in
[our GitHub organisation][github] and can find the package available to [download at JustEat.HttpClientInterception on NuGet.org][nuget].

READMORE

## The Problem

Many modern software applications integrate with external Application Programming Interfaces (APIs) to provide
solutions for problems within their domain of responsibility, whether it be delivering your night in, booking a
flight, trading financial instruments, or monitoring transport infrastructure in real-time.

These APIs are very often HTTP-based, with RESTful APIs that consume and produce JSON often being the implementation
of choice. Of course integrations might not be with full-blown APIs, but just external resources that are available
over HTTP, such as a website exposing HTML, or a file download portal. In .NET applications, whether these are
console-based, rich GUIs, background services, or ASP.NET web apps, a common go-to way of consuming such HTTP-based
services from code is using the [`HttpClient`][httpclient] class.

`HttpClient` provides a simple API surface for `GET`-ing and `POST`-ing resources over HTTP(S) to external services
in many different data formats, as well as functionality for reading and writing HTTP headers and more advanced
extensibility capabilities. It is also commonly exposed as a dependency that can be injected into other services, such
as third-party dependencies for tasks such as implementing OAuth-based authentication.

Overall this makes `HttpClient` a common and appropriate choice for writing HTTP-based integrations for .NET applications.

An important part of software development is not just implementing a solution to a given problem, but also writing tests
for your applications. A good test suite helps ensure that delivered software is of a high quality, is functionally
correct, is resilient in the face of failure, and provides a safety net against regression for future work.

When your application depends on external resources though, then testing becomes a bit more involved. You don’t want
to have the code under test making network calls to these external services for a myriad of reasons. They make your tests
brittle and hard to maintain, require a network connection to be able to run successfully, might cost you money, and slow
down your test suite, to name but a few examples.

These issues lead to approaches using things like mocks and stubs. `HttpClient`, and its more low-level counterpart
[`HttpMessageHandler`][httpmessagehandler], are not simple to mock however. While not difficult to do, their lack of
interface and design lead to a requirement to implement classes that derive from `HttpMessageHandler` in order to override
`protected` members to drive test scenarios, and to build non-primitive types by hand, such as [`HttpResponseMessage`][httpresponsemessage].

Another approach that can be used to simplify the ability to use mocks is to create your own custom `IHttpClient`
interface and wrap your usage of `HttpClient` within an implementation of this interface. This creates its own problems
in non-trivial integrations though, with the interface often swelling to the point of being a one-to-one representation
of `HttpClient` itself to expose enough functionality for your use-cases.

While this mocking and wrapping is feasible, once your application does more than one or two simple interactions with
an HTTP-based service, the amount of test code required to drive your test scenarios can balloon quite quickly and
become a burden to maintain.

It is also an approach that only works in a typical unit test approach. As usage of `HttpClient` is typically fairly
low down in your application’s stack, this does not make it a viable solution for other test types, such as functional
and integration tests.

## A Solution

Today we’re publishing a way of solving some of these problems by releasing our JustEat.HttpClientInterception .NET library
as open-source to our [organisation in GitHub.com][github] under the Apache 2.0 licence.

A compiled version of the .NET assembly is also available from [JustEat.HttpClientInterception on NuGet.org][nuget]
that supports .NET Standard 1.3 (and later) and .NET Framework 4.6.1 (and later).

`JustEat.HttpClientInterception` provides a number of types that allow HTTP requests and their corresponding responses
to be declared using the [builder pattern][builder-pattern] to register interceptions for HTTP requests in your code to
bypass the network and return responses that drive your test scenarios.

Below is a simple example that shows registering an interception for an HTTP GET request to the Just Eat Public API:

```
// Install-Package JustEat.HttpClientInterception
// using JustEat.HttpClientInterception;
var builder = new HttpRequestInterceptionBuilder()
    .ForHost("public.je-apis.com")
    .ForPath("terms")
    .WithJsonContent(new { Id = 1, Link = "https://www.just-eat.co.uk/privacy-policy" });

var options = new HttpClientInterceptorOptions().Register(builder);

// Create an instance of HttpClient to make requests with
var client = options.CreateHttpClient();

// The value of the json variable will be "{\"Id\":1,\"Link\":\"https://www.just-eat.co.uk/privacy-policy\"}"
var json = await client.GetStringAsync("http://public.je-apis.com/terms");
```

The library provides a strongly-typed API that supports easily setting up interceptions for arbitrary HTTP requests to
any URL and for any HTTP verb, returning responses that consist of either raw bytes, strings or objects that are serialized
into a response as-and-when they are required.

Fault injection is also supported by allowing arbitrary HTTP codes to be set for intercepted responses, as well as
latency injection via an ability to specify a custom asynchronous call-back that is invoked before the intercepted
response is made available to the code under test.

With ASP.NET Core adding [Dependency Injection as a first-class feature][dependency-injection] and being
[easy to self-host for use within test projects][integration-testing], a small number of changes to your production code
allows `HttpClientInterceptorOptions` to be injected into your application’s dependencies for use with integration tests
without your application needing to take a dependency on `JustEat.HttpClientInterception` for itself.

With the library injected into the application, HTTP requests using `HttpClient` and/or `HttpMessageHandler` that are
resolved by your IoC container of choice can be inspected and intercepted as-required before any network connections
are made. You can also opt-in to behaviour that throws an exception for any un-intercepted requests, allowing you to
flush out all HTTP requests made by your application from your tests.

Further examples of using the library can be found at these links:

- [In the project’s README file][readme]
- [A sample application][sample]
- [Some example tests][examples]

## The Benefits

We’ve used this library successfully with two internal applications we’re developing with ASP.NET Core (one an API, the
other an MVC website) to really simplify our tests, and provide good code coverage, by using a test approach that is
primarily a [black-box approach][black-box-testing].

The applications’ test suites self-host the application using [Kestrel][kestrel], with the service registration set-up to
create a chain of [`DelegatingHandler`][delegatinghandler] implementations when resolving instances of `HttpClient` and
`HttpMessageHandler`. With `HttpClientInterceptorOptions` registered to provide instances of `DelegatingHandler` by the
test start-up code when the application is self-hosted, this allows all HTTP calls within the self-hosted application in
the tests to be intercepted to drive the tests.

The tests themselves then either initiate HTTP calls to the public surface of the self-hosted server with a vanilla
`HttpClient` in the case of the API, or use Selenium to test the rendered pages using browser automation in the case of
the website.

This approach provides many benefits, such as:

- Simple setup for testing positive and negative code paths for HTTP responses, such as for error handling.
- Exercises serialization and deserialization code for HTTP request and response bodies.
- Testing behaviour in degraded scenarios, such as network latency, for handling of timeouts.
- Removes dependencies on external services for the tests to pass and the need to have access to an active network connection for services that may only be resolvable on a internal/private network.
- No administrative permissions required to set-up port bindings.
- Speeds up test execution by removing IO-bound network operations.
- Allows you to skip set-up steps to create test data for CRUD operations, such as having to create resources to test their deletion.
- Can be integrated in a way that other delegating handlers your application may use are still exercised and tested implicitly.
- Allows us to intercept calls to IdentityServer for our user authentication and issue valid self-signed JSON Web Tokens (JWTs) in the tests to authenticate browser calls in Selenium tests.

In the case of the ASP.NET Core API using this test approach, at the time of writing, we’ve been able to achieve over
90% statement coverage of a several thousand line application with just over 200 unit, integration and end-to-end tests.
Using our TeamCity server, the build installs the .NET Core runtime, restores its dependencies from NuGet, compiles all
the code and runs all the tests in just over three-and-a-half minutes.

## Some Caveats

Of course such a solution is not a silver bullet. Intercepting all of your HTTP dependencies isolates you from interface
changes in your dependencies.

If an external service changes its interfaces, such as by adding a new API version or deprecating the one you use, adds new
fields to the responses, or changes to require all traffic to support HTTPS instead of HTTP, your integration tests will not
find such changes. It also does not validate that your application integrates correctly with APIs that require authentication
or apply rate-limits.

Similarly, the black-box approach is relatively heavyweight compared to a simple unit test, so may not be suited to testing
all of the edge cases in your code and low-level assertions on your responses.

Finally, your intercepted responses will only cater for the behaviour you’ve seen and catered-for within your tests. A real
external dependency may change its behaviour over time in ways that your static simulated behaviours will not necessarily emulate.

A good mixture of unit, interception-based integration tests, and end-to-end tests against your real dependencies are needed
to give you a good robust test suite that runs quickly and also gives you confidence in your changes as you develop your
application over time. Shipping little and often is a key tenet of Continuous Delivery.

## In Conclusion

We hope that you’ve found this blog post interesting and that you find `JustEat.HttpClientInterception` useful in your own
test suites for simplifying things and making your applications even more awesome.

You can find the project in our [organisation on GitHub][github] and you can download the library to use in your .NET projects
from [the JustEat.HttpClientInterception package page on NuGet.org][nuget].

Contributions to the library are welcome – check out the [contributing guide][contributing] if you’d like to get involved!

[black-box-testing]: https://en.wikipedia.org/wiki/Black-box_testing
[builder-pattern]: https://en.wikipedia.org/wiki/Builder_pattern
[contributing]: https://github.com/justeattakeaway/httpclient-interception/blob/main/.github/CONTRIBUTING.md
[delegatinghandler]: https://learn.microsoft.com/dotnet/api/system.net.http.delegatinghandler
[dependency-injection]: https://learn.microsoft.com/aspnet/core/fundamentals/dependency-injection
[examples]: https://github.com/justeattakeaway/httpclient-interception/blob/main/tests/HttpClientInterception.Tests/Examples.cs
[github]: https://github.com/justeattakeaway/httpclient-interception
[httpclient]: https://learn.microsoft.com/dotnet/api/system.net.http.httpclient
[httpmessagehandler]: https://learn.microsoft.com/dotnet/api/system.net.http.httpmessagehandler
[httpresponsemessage]: https://learn.microsoft.com/dotnet/api/system.net.http.httpresponsemessage
[integration-testing]: https://learn.microsoft.com/aspnet/core/test/integration-tests
[kestrel]: https://learn.microsoft.com/aspnet/core/fundamentals/servers/kestrel
[nuget]: https://www.nuget.org/packages/JustEat.HttpClientInterception/
[original-post]: https://web.archive.org/web/20240622022707/https://tech.justeattakeaway.com/2017/10/02/reliably-testing-http-integrations-in-a-dotnet-application/
[readme]: https://github.com/justeattakeaway/httpclient-interception?tab=readme-ov-file#basic-examples
[sample]: https://github.com/justeattakeaway/httpclient-interception/tree/main/samples#httpclient-interception-samples
