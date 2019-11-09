---
title: Integration testing AWS Lambda C# Functions with Lambda Test Server
date: 2019-11-11
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

Under-the-hood, the AWS Lambda runtime works by exposing an HTTP API with four resources that are used to drive a message-pump that processes messages, one of which is an "input" with the other three being for "output".

The runtime code effectively runs an infinite while-loop which calls the `GET /{LambdaVersion}/runtime/invocation/next` resource and processes the content of the HTTP responses. The responses also contain metadata about the function in the headers, such as the AWS request Id and function ARN.

Once the function code has handled the request, the runtime either calls the `POST /{LambdaVersion}/runtime/invocation/{AwsRequestId}/response` resource for successfully processed requests or the `POST /{LambdaVersion}/runtime/invocation/{AwsRequestId}/error` resource to report errors. The fourth resource is used to handle failed function initialization: `POST /{LambdaVersion}/runtime/init/error`.

In theory this works like HTTP long-polling within your function, but in practice the AWS Lambda Runtime freezes the function process if there are no pending messages to invoke your code with.

Equipped with this knowledge and further reverse-engineering of the .NET Lambda runtime support, I started to implement _Lambda Test Server_, starting with a [proof-of-concept](https://github.com/martincostello/alexa-london-travel/pull/139 "Add Lambda test server for integration tests") which I committed directly into the repo for my Alexa skill.

It uses ASP.NET Core 3.0's `TestServer` and [endpoint routing](https://docs.microsoft.com/en-us/aspnet/core/fundamentals/routing?view=aspnetcore-3.0 "Routing in ASP.NET Core") to implement an in-memory HTTP server exposed via `HttpClient`. Requests for the Lambda to process can be queued with the server and are delivered to the message pump sequentially over "HTTP" and are then passed to the Lambda function being tested, with the response being posted back into the runtime.

Here's an excerpt of the code (tweaked for brevity) that sets up the HTTP endpoints for the emulated Lambda runtime:

```
protected virtual void Configure(IApplicationBuilder app)
{
    app.UseRouting();
    app.UseEndpoints(endpoints =>
    {
        endpoints.MapGet("/{Version}/runtime/invocation/next", OnNext);
        endpoints.MapPost("/{Version}/runtime/init/error", OnInitializationError);
        endpoints.MapPost("/{Version}/runtime/invocation/{RequestId}/error", OnInvocationError);
        endpoints.MapPost("/{Version}/runtime/invocation/{RequestId}/response", OnResponse);
    });
}
```

The requests to pass to the function that are queued into the test server are routed through into a `Channel<T>` which provides a reader and writer that acts as a producer-consumer pair. Enqueueing a message places it into the `ChannelWriter<T>`, while the message pump consumes the `ChannelReader<T>`. You can read more about .NET's Channels in [this post](https://www.stevejgordon.co.uk/an-introduction-to-system-threading-channels "An Introduction to System.Threading.Channels") by fellow Microsoft MVP [Steve Gordon](https://twitter.com/stevejgordon "Steve Gordon on Twitter").

Once I was happy with the basic concept and had it working with my Alexa skill's codebase, I started the process to make it its own open source repository and to publish it as a NuGet package. Once I'd shipped [version 0.1.0](https://github.com/martincostello/lambda-test-server/releases/tag/v0.1.0 "AWS Lambda Test Server v0.1.0") I then just needed to circle back around to my skill and [delete the proof-of-concept and consume the library instead](https://github.com/martincostello/alexa-london-travel/pull/140 "Use AwsLambdaTestServer NuGet package").

## Improvements

In the course of implementing the first version there were a few bits I wasn't entirely happy with.

The first was that I needed to use reflection to pass the `HttpClient` for the test server into `LambdaBootstrap` to get it wired-up. Not the end of the world, but not as neat as it could be.

The second was that the Lambda runtime loop, depending on timing, would always wait for at least one more new message to process before terminating, even if your test was completed and didn't need to queue anything new. I worked around this by having the test server deliver a "fake" message with empty content to "break" the loop.

These seemed like simple enough things to alter, and with the AWS SDK for Lambda being open source on GitHub, I [submitted a Pull Request](https://github.com/aws/aws-lambda-dotnet/pull/540 "Suggested changes for LambdaBootstrap testability") to refactor things a bit to allow the `HttpClient` to be injected and to have the message loop observe a thrown `OperationCanceledException` if the cancellation token for the loop was signalled.

[Stuart Lang](https://twitter.com/stuartblang "Stuart Lang on Twitter"), an ex-colleague of mine I'd been discussing the test server with, [reached out](https://twitter.com/stuartblang/status/1190949491781914624) to AWS' [Norm Johanson](https://twitter.com/socketnorm "Norm Johanson on Twitter") to have him look at the PR. Just under three days later the PR had been merged and a new version of the library pushed to NuGet.org!

<blockquote class="twitter-tweet" align="center"><p lang="en" dir="ltr">Closing the loop on this the PR has been released as part of version 1.1.0 of Amazon.Lambda.RuntimeSupport <a href="https://t.co/qJhP9B2hoB">pic.twitter.com/qJhP9B2hoB</a></p>&mdash; Norm Johanson (@socketnorm) <a href="https://twitter.com/socketnorm/status/1191966966183018496?ref_src=twsrc%5Etfw">November 6, 2019</a></blockquote> <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>

With the changes made available, all that was left was to [remove the workarounds](https://github.com/martincostello/lambda-test-server/pull/17 "Remove workarounds for Amazon.Lambda.RuntimeSupport"), publish an updated version of _Lambda Test Server_ and [update my Alexa skill's tests to use it](https://github.com/martincostello/alexa-london-travel/pull/152 "Update lambda-test-server").

## Conclusion

Using _Lambda Test Server_ in my Alexa skill, as well as the EC2 to Lambda migration at work, has allowed me to write a small number of acceptance-style integration tests for some Lambda functions while also providing high code coverage (>80%) and being able to target .NET Core 3.0 without the native Lambda runtime support.

At the same time it's also taught me some things about how the AWS Lambda runtime works internally, which I've found interesting, as well as some experience coding with channels and the lower-level endpoint routing in ASP.NET Core 3.0 without the weight of using MVC controllers.

With just some small refactoring of your function entrypoint to accept an `HttpClient` and `CancellationToken`, you can really boost the amount of code you can quickly test locally before committing your code to Continuous Integration and deploying it to your AWS account.

I hope you find it useful in your own .NET Core Lambda functions!
