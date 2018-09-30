---
title: Writing Logs to xunit Test Output
date: 2018-09-30
tags: logging,testing,xunit
layout: bloglayout
description: "How to write the logs from your xunit tests to the test output."
---

Today I've published a NuGet package that simplifies the mechanics of writing logs to the test output for xunit tests, `MartinCostello.Logging.XUnit` v0.1.0. It's open-source with an Apache 2.0 licence and available on GitHub.

  * [NuGet package](https://www.nuget.org/packages/MartinCostello.Logging.XUnit/ "MartinCostello.Logging.XUnit on NuGet.org")
  * [GitHub repository](https://github.com/martincostello/xunit-logging "MartinCostello.Logging.XUnit on GitHub.com")

Pull Requests and questions are welcome over on GitHub - I hope you find it useful!

READMORE

## Rationale

If you're a .NET/C# developer, it's quite likely (I hope!) that you write and run a lot of unit and integration tests for the applications you work on as part of your day-to-day job. There's also a good chance if you're working on a new or recently created application that you're using [.NET Core](https://dot.net ".NET Core website") and [xunit](https://xunit.github.io/ "xUnit.net website") to code the application and do your automated testing.

When tests (particularly integration/functional tests) fail, sometimes it can be difficult to trace what's gone wrong. For example, in an integration test where you treat the application as a [_black box_](https://en.wikipedia.org/wiki/Black-box_testing "Black-box testing on Wikipedia") and call through the public interfaces (web pages, HTTP endpoints etc.), the failing assertion might be just an HTTP 500 error saying that something's gone wrong. While that might be the correct public-facing behaviour for the application when it experiences a failure, that doesn't make it particularly easy for you as a developer to determine _why_ the test failed so you can fix it.

Now there's probably some logging in your application that catch exceptions and log them, but these aren't usually readily available in the test results in situations like these, with you needing to debug the application to find the source of the test failure. In continuous integration environments this can be even more difficult, with it not easy to debug tests or collect logs.

I've had this problem in .NET Core applications I've worked on, and I found myself writing boring, duplicated boilerplate code to handle bridging the application's logs to xunit for easier test failure analysis again and again. Eventually I decided I should stop repeating myself and make a library to make it easy to light-up this kind of functionality in my tests and make my life a little easier and more efficient.

## Solution

[`MartinCostello.Logging.XUnit`](https://www.nuget.org/packages/MartinCostello.Logging.XUnit/ "MartinCostello.Logging.XUnit on NuGet.org") is based purely on my own use-cases for testing and the functionality is quite simple, so the first version is being published as a `0.1.0` rather than a `1.0.0`, but it's stable and has been dog-fooded in a number applications I work on for my job ([ASP.NET Core 2.1 – Supercharging Our Applications](https://tech.just-eat.com/2018/06/14/aspnet-core-21-supercharging-our-applications/ "ASP.NET Core 2.1 – Supercharging Our Applications")), as well as in my own personal projects, such as [SQL LocalDB Wrapper](https://github.com/martincostello/sqllocaldb "SQL LocalDB Wrapper on GitHub.com") and Alexa London Travel's [website](https://github.com/martincostello/alexa-london-travel-site "alexa-london-travel-site on GitHub.com").

Here's an example of using it to create an `ILogger<T>` for a class being tested with xunit:

```
using System;
using Microsoft.Extensions.DependencyInjection;
using Microsoft.Extensions.Logging;
using Xunit;
using Xunit.Abstractions;

namespace MyApp.Calculator
{
    public class CalculatorTests
    {
        // Pass ITestOutputHelper into the test class, which xunit provides per-test
        public CalculatorTests(ITestOutputHelper outputHelper)
        {
            OutputHelper = outputHelper;
        }

        private ITestOutputHelper OutputHelper { get; }

        [Fact]
        public void Calculator_Sums_Two_Integers()
        {
            // Arrange - Create a service collection and call AddXunit()
            // on the logging builder to register it as a logging provider.
            var services = new ServiceCollection()
                .AddLogging((builder) => builder.AddXUnit(OutputHelper))
                .AddSingleton<Calculator>();

            // Get the system-under-test (the Calculator) from the service collection.
            // This will be created with a logger that routes to the xunit test output.
            var calculator = services
                .BuildServiceProvider()
                .GetRequiredService<Calculator>();

            // Act
            int actual = calculator.Sum(1, 2);

            // Assert
            Assert.AreEqual(3, actual);
        }
    }

    public sealed class Calculator
    {
        private readonly ILogger _logger;

        public Calculator(ILogger<Calculator> logger)
        {
            _logger = logger;
        }

        public int Sum(int x, int y)
        {
            int sum = x + y;

            _logger.LogInformation("The sum of {x} and {y} is {sum}.", x, y, sum);

            return sum;
        }
    }
}
```

As you can see below, the logging output is available in the test results in Visual Studio. If the test were to fail, the output would also be written to the console, such as to diagnose a failing test running in [AppVeyor](https://www.appveyor.com/ "AppVeyor website").

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/xunit-logging-vs-test-output.png" alt="Test output in Visual Studio" title="Test output in Visual Studio">

There's also an example of registering the logger for a self-hosted ASP.NET Core application using `WebApplicationFactory<T>` for functional tests in the [sample integration tests](https://github.com/martincostello/xunit-logging/blob/c83d15591df4b5b31f2b40ee43d9b67cf8d628d5/tests/Logging.XUnit.Tests/Integration/HttpApplicationTests.cs "Example HTTP integration tests") in the library's own test project using a sample application.

Further real usage of the library for both a library and an ASP.NET Core application are available for reference in the GitHub repositories linked to below:
  * [martincostello/sqllocaldb](https://github.com/martincostello/sqllocaldb/blob/fc3cd5d8539b5c8bb9d86896f0a2eae37ab6fa24/samples/TodoApp.Tests/TodoRepositoryTests.cs "martincostello/sqllocaldb sample tests on GitHub.com")
  * [martincostello/alexa-london-travel-site](https://github.com/martincostello/alexa-london-travel-site/tree/c43c297d903c04196cc8eb66caf70b1cb32aef25/tests/LondonTravel.Site.Tests/Integration "martincostello/alexa-london-travel-site integration tests on GitHub.com")
