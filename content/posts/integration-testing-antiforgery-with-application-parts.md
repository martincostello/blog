---
title: Integration Testing ASP.NET Core Resources Protected with Antiforgery Using Application Parts
date: 2020-06-16
tags: aspnet,aspnetcore,antiforgery,csrf,dotnet,testing,xsrf
layout: post
description: "Using ASP.NET Core Application Parts to simplify testing of HTTP resources that are protected by antiforgery features."
---

To protect your POST resources in an ASP.NET Core application from [Cross-Site Request Forgery](https://docs.microsoft.com/en-us/aspnet/core/security/anti-request-forgery "Prevent Cross-Site Request Forgery (XSRF/CSRF) attacks in ASP.NET Core") (CSRF) an application developer would typically use the [antiforgery](https://docs.microsoft.com/en-us/aspnet/core/security/anti-request-forgery#aspnet-core-antiforgery-configuration "ASP.NET Core antiforgery configuration") features to require an antiforgery token and cookie are included in HTTP POST form requests.

A necessary downside of these protections is that they make it harder to integration test such resources, particularly in a headless manner. This is because the tests need to acquire the antiforgery token and cookie to be able to successfully pass the antiforgery protections on a resource that needs to be tested.

A typical approach for this is to scrape the HTML response from the application for the hidden form field token (often named `__RequestVerificationToken`) using Regular Expressions and then using that, along with the cookie, in the request(s) the test(s) make. This can however make tests brittle to change, particularly if the UI is refactored.

In this blog post I'll discuss an alternate approach using [ASP.NET Core Application Parts](https://docs.microsoft.com/en-us/aspnet/core/mvc/advanced/app-parts "Share controllers, views, Razor Pages and more with Application Parts") that can make such tests easier to author and maintain, allowing you to concentrate on the core logic of your tests, rather than boilerplate setup.

<!--more-->

## Motivation

I've recently been reimplementing part of a .NET Framework web application over to a newer ASP.NET Core 3.1 web application. The part of the application in question requires a write operation, so uses an HTML form for the request to be made by the user so that it is safe from CSRF attacks.

The operation can have various different responses, so there's many different cases to cover as part of automated integration testing. I've blogged about [HTTP integration testing before](https://blog.martincostello.com/reliably-testing-http-integrations-in-dotnet-applications/ "Reliably Testing HTTP Integrations in a .NET Application") and using [`WebApplicationFactory<T>`](https://docs.microsoft.com/en-us/aspnet/core/test/integration-tests "Integration tests in ASP.NET Core"), but the need to pass the antiforgery protections presented a bit of a challenge.

One approach I've used in the past is to add _"test-only"_ controller methods to the application, but then care has to be taken to ensure that these can't be used in production to bypass security features. It also bloats the code of the service itself, having to be deployed with code that isn't actually used.

It occurred to me that a better way to manage this would be instead to use Application Parts to inject the test resources into the application at runtime as part of the tests. This allows us to add helper features to the application for use with the integration tests, while keeping the production code itself clean and uncluttered by additional test infrastructure and code paths.

While I can't share the code for the application itself, I have put a sample application together that shows the approach in action for both web forms and JSON endpoints for HTTP POST and DELETE operations up on GitHub: [https://github.com/martincostello/antiforgery-testing-application-part](https://github.com/martincostello/antiforgery-testing-application-part "antiforgery-testing-application-part on GitHub.com")

## How It Works

The sample application is just a simple TODO list application that gives us something to demonstrate the approach against, but the concepts should work for any type of ASP.NET Core application using antiforgery features.

The test project contains an [`AntiforgeryTokenController`](https://github.com/martincostello/antiforgery-testing-application-part/blob/5d8ed60e8874dc8403bb43a404a5a540362e5d07/tests/TodoApp.Tests/AntiforgeryTokenController.cs#L16 "AntiforgeryTokenController class") class. This contains an [HTTP GET resource](https://github.com/martincostello/antiforgery-testing-application-part/blob/f8985fe1bbaa800cf73bc62bb85949c1c0a8a698/tests/TodoApp.Tests/AntiforgeryTokenController.cs#L39-L65 "GET action to get valid CSRF tokens") that uses the antiforgery features to return a JSON payload containing valid CSRF tokens and the relevant cookie/form/header names to use to validate requests:

```
public IActionResult GetAntiforgeryTokens(
    [FromServices] IAntiforgery antiforgery,
    [FromServices] IOptions<AntiforgeryOptions> options)
{
    AntiforgeryTokenSet tokens = antiforgery.GetTokens(HttpContext);

    var model = new AntiforgeryTokens()
    {
        CookieName = options.Value.Cookie.Name,
        CookieValue = tokens.CookieToken,
        FormFieldName = options.Value.FormFieldName,
        HeaderName = tokens.HeaderName,
        RequestToken = tokens.RequestToken,
    };

    return Json(model);
}
```

This is then configured as an Application Part by the [`ConfigureAntiforgeryTokenResource()`](https://github.com/martincostello/antiforgery-testing-application-part/blob/f8985fe1bbaa800cf73bc62bb85949c1c0a8a698/tests/TodoApp.Tests/IWebHostBuilderExtensions.cs#L26-L39 "ConfigureAntiforgeryTokenResource method") method, which is [registered with the test server fixture](https://github.com/martincostello/antiforgery-testing-application-part/blob/f8985fe1bbaa800cf73bc62bb85949c1c0a8a698/tests/TodoApp.Tests/TestServerFixture.cs#L82 "TestServer registration"):

```
protected override void ConfigureWebHost(IWebHostBuilder builder)
{
    builder.ConfigureAntiforgeryTokenResource();
}
```

One potential gotcha to watch out for is to make sure that requests to the test controller don't return an HTTP 404. To fix this, make sure that the assembly containing the test controllers is decorated with the [`[ApplicationPart]` attribute](https://github.com/martincostello/antiforgery-testing-application-part/blob/5d8ed60e8874dc8403bb43a404a5a540362e5d07/tests/TodoApp.Tests/TodoApp.Tests.csproj#L24-L31 "Adding the ApplicationPart attribute"). One way you can achieve this is with adding a snippet like the below to your test project's `.csproj` file:

```
<!--
  Add [ApplicationPart("TodoApp.Tests")] to the assembly so the controller is discovered.
-->
<ItemGroup>
  <AssemblyAttribute Include="Microsoft.AspNetCore.Mvc.ApplicationParts.ApplicationPartAttribute">
    <_Parameter1>TodoApp.Tests</_Parameter1>
  </AssemblyAttribute>
</ItemGroup>
```

Thanks to [Andrew Lock's blog post on Application Parts](https://andrewlock.net/when-asp-net-core-cant-find-your-controller-debugging-application-parts/#what-are-application-parts- "When ASP.NET Core can't find your controller: debugging application parts") for pointing me to towards the fix.

This then allows tests to use the [`GetAntiforgeryTokensAsync()`](https://github.com/martincostello/antiforgery-testing-application-part/blob/f8985fe1bbaa800cf73bc62bb85949c1c0a8a698/tests/TodoApp.Tests/TestServerFixture.cs#L52-L64 "GetAntiforgeryTokensAsync method") helper method to perform an HTTP GET to the application to obtain valid CSRF tokens to use:

```
public async Task<AntiforgeryTokens> GetAntiforgeryTokensAsync()
{
    using var httpClient = CreateClient();
    using var response = await httpClient.GetAsync(AntiforgeryTokenController.GetTokensUri);

    return JsonSerializer.Deserialize<AntiforgeryTokens>(await response.Content.ReadAsStringAsync());
}
```

The tests then use this to configure an `HttpClient` with CSRF tokens so that HTTP POST/DELETE etc. requests to the application pass the checks by the antiforgery protections.

```
[Fact]
public async Task Can_Create_Todo_Item_With_Html_Form()
{
    // Arrange - Get valid CSRF tokens and parameter names from the server
    AntiforgeryTokens tokens = await Fixture.GetAntiforgeryTokensAsync();

    // Configure a handler with the CSRF cookie
    using var cookieHandler = new CookieContainerHandler();
    cookieHandler.Container.Add(
        Fixture.Server.BaseAddress,
        new Cookie(tokens.CookieName, tokens.CookieValue));

    // Create an HTTP client and add the CSRF cookie
    using var httpClient = Fixture.CreateDefaultClient(cookieHandler);

    // Create form content to create a new item with the CSRF parameter added
    var form = new Dictionary<string, string>()
    {
        [tokens.FormFieldName] = tokens.RequestToken,
        ["text"] = "Buy milk",
    };

    // Act - Create a new list item
    using var content = new FormUrlEncodedContent(form);
    using var response = await httpClient.PostAsync("home/additem", content);

    // Assert - The item was created
    response.StatusCode.ShouldBe(HttpStatusCode.Redirect);
}
```

With these building blocks in place, it's then quite easy to iterate on to add test cases for all of the relevant endpoints and get good code coverage for all the different scenarios.

## Conclusion

I've found this approach quite a neat solution to being able to test resources with antiforgery protections, so I figured I'd share the approach with the wider .NET community.

Using a variant of this approach allowed me to quickly add a variety of test cases for the feature I was working on migrating to ensure it was robust and well-tested, giving much more confidence in the work. At the same time, it removed the need to have brittle HTML scraping code, or even the need to have a UI for the back-end written at all before being able to start integration testing.

I hope you've found this post interesting and useful - happy coding!
