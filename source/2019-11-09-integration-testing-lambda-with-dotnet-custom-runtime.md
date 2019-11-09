---
title: Integration testing AWS Lambda C# Functions with Lambda Test Server
date: 2019-11-09
tags: aws,dotnet,lambda,testing
layout: bloglayout
description: "Using Lambda Test Server to integration test your C# AWS Lambda functions for .NET Core locally when using a custom runtime."
image: "https://cdn.martincostello.com/blog_lambda-dotnet-core.png"
---

_Lambda Test Server_ is a .NET Core 3.0 library available from [NuGet](https://www.nuget.org/packages/MartinCostello.Testing.AwsLambdaTestServer/ "MartinCostello.Testing.AwsLambdaTestServer on NuGet.org") which builds on top of the [`TestServer`](https://docs.microsoft.com/en-us/dotnet/api/microsoft.aspnetcore.testhost.testserver "TestServer Class on Microsoft Docs") class in the [Microsoft.AspNetCore.TestHost NuGet package](https://www.nuget.org/packages/Microsoft.AspNetCore.TestHost/ "Microsoft.AspNetCore.TestHost on NuGet.org") to provide infrastructure to use with end-to-end/integration tests for .NET Core AWS Lambda Functions using a [custom runtime](https://aws.amazon.com/blogs/developer/net-core-3-0-on-lambda-with-aws-lambdas-custom-runtime/ ".NET Core 3.0 on Lambda with AWS Lambda’s Custom Runtime").

The example below shows how _Lambda Test Server_ can be used to write an xunit integration test for a simple C# Lambda function that reverses an array of integers:

```
[Fact]
public static async Task Function_Reverses_Numbers()
{
    // Arrange
    using var server = new LambdaTestServer();
    using var cancellationTokenSource = new CancellationTokenSource(TimeSpan.FromSeconds(1));

    await server.StartAsync(cancellationTokenSource.Token);

    int[] value = new[] { 1, 2, 3 };
    string json = JsonConvert.SerializeObject(value);

    LambdaTestContext context = await server.EnqueueAsync(json);

    using var httpClient = server.CreateClient();

    // Act
    await ReverseFunction.RunAsync(httpClient, cancellationTokenSource.Token);

    // Assert
    Assert.True(context.Response.TryRead(out LambdaTestResponse response));
    Assert.True(response.IsSuccessful);

    json = await response.ReadAsStringAsync();
    int[] actual = JsonConvert.DeserializeObject<int[]>(json);

    Assert.Equal(new[] { 3, 2, 1 }, actual);
}
```

The source is available [in GitHub](https://github.com/martincostello/lambda-test-server "Lambda Test Server on GitHub.com") - pull requests are welcome!

Further samples for using the library with xunit are available in GitHub here: [https://github.com/martincostello/lambda-test-server/tree/master/samples](https://github.com/martincostello/lambda-test-server/tree/master/samples "Lambda Test Server samples on GitHub.com")

READMORE

## Background

Back in mid-October I received an email from AWS stating that my Lambda functions using Node.js 8.10 needed to be updated to version 10 as 8.10 was going to be [deprecated at the start of 2020](https://docs.aws.amazon.com/lambda/latest/dg/runtime-support-policy.html "AWS Lambda Runtime Support Policy").

I have a few Lambda@Edge functions that are used for [this blog](https://blog.martincostello.com/migrating-from-iis-to-s3/ "Migrating to Amazon S3") that were easy enough to update, but I also have a published Alexa skill, [_London Travel_](https://www.amazon.co.uk/Martin-Costello-London-Travel/dp/B01NB0T86R "London Travel Alexa skill on amazon.co.uk"), that was also using node 8.10 that isn't as simple.

While it was still [very easy](https://github.com/martincostello/alexa-london-travel/pull/123 "Update to Node.js 10") to migrate the function for Node.js 10, I thought it might be a good opportunity to take the time to rewrite the Lambda function that powers the skill in C# using .NET Core. I had some tasks coming up at work to migrate various .NET Core 3.0 worker services running on EC2 that my team maintains to be Lambda functions, so I figured this would be a good learning opportunity before tackling some high-throughput production workloads.

So first I [rewrote the skill in C#](https://github.com/martincostello/alexa-london-travel/pull/124 "Rewrite skill as .NET Core") targeting .NET Core 2.1 and deployed it, then I started looking into the upgrade to 3.0.

AWS Lambda has built-in support for .NET Core 2.1, which at the time of writing is the Long Term Service (LTS) version, but no runtime support for .NET Core 2.2 or the [recently released](https://devblogs.microsoft.com/dotnet/announcing-net-core-3-0/ "Announcing .NET Core 3.0") .NET Core 3.0. Even though support for .NET Core 3.x is likely to be available in the next few months when .NET Core 3.1 is released (which will be the [next LTS release](https://devblogs.microsoft.com/dotnet/announcing-net-core-3-1-preview-1/ "Announcing .NET Core 3.1 Preview 1")), I didn't want to wait around to be able to benefit from .NET Core 3.0's various [performance improvements](https://devblogs.microsoft.com/dotnet/performance-improvements-in-net-core-3-0/ "Performance Improvements in .NET Core 3.0").

AWS does however allow you to bring your own runtime for Lambda using [custom runtimes](https://docs.aws.amazon.com/lambda/latest/dg/runtimes-custom.html "Custom AWS Lambda Runtimes") and the `LambdaBootstrap` class from the [Amazon.Lambda.RuntimeSupport](https://www.nuget.org/packages/Amazon.Lambda.RuntimeSupport/ "Amazon.Lambda.RuntimeSupport on NuGet.org") NuGet package. Following the [AWS blog post](https://aws.amazon.com/blogs/developer/net-core-3-0-on-lambda-with-aws-lambdas-custom-runtime/ ".NET Core 3.0 on Lambda with AWS Lambda’s Custom Runtime") about .NET Core 3.0 as well as my colleague [Zac Charles'](https://twitter.com/zaccharles "Zac Charles on Twitter") [recommendations for .NET Core 3.0 Lambdas](https://medium.com/@zaccharles/net-core-3-0-aws-lambda-benchmarks-and-recommendations-8fee4dc131b0 ".NET Core 3.0 AWS Lambda Benchmarks and Recommendations"), I updated the function to [target .NET Core 3.0](https://github.com/martincostello/alexa-london-travel/pull/137 "Update to .NET Core 3.0") and deployed it too.

It was at this point with the EC2 to Lambda migration work about to start that I started thinking about how to make it easier to integration test such a Lambda without having to deploy the code into the AWS Lambda runtime first. This wasn't about AWS hosting costs, but about helping to _"shift left"_ the testing - decreasing the cycle time for iterating on the implementation of the Lambda functions as I wrote the code on my laptop.

After looking through [the code](https://github.com/aws/aws-lambda-dotnet/tree/master/Libraries/src/Amazon.Lambda.RuntimeSupport "Amazon.Lambda.RuntimeSupport on GitHub.com") for `LambdaBootstrap` and seeing what it did, I decided to write a test server that emulated the AWS Lambda runtime environment to allow running the code as close to a _"black box"_ as possible in tests to validate the Lambda function.

## How It Works

_TODO_

## Improvements

_TODO_

## Conclusion

_TODO_

<!--

[](https://github.com/martincostello/alexa-london-travel/pull/139 "Add Lambda test server for integration tests")

[](https://github.com/martincostello/alexa-london-travel/pull/140 "Use AwsLambdaTestServer NuGet package")

[](https://github.com/aws/aws-lambda-dotnet/pull/540 "Suggested changes for LambdaBootstrap testability")

[](https://github.com/martincostello/lambda-test-server/pull/17 "Remove workarounds for Amazon.Lambda.RuntimeSupport")

[](https://github.com/martincostello/alexa-london-travel/pull/152 "Update lambda-test-server")

[](https://twitter.com/stuartblang "Stuart Lang on Twitter")

[](https://twitter.com/socketnorm "Norm Johanson on Twitter")

-->
