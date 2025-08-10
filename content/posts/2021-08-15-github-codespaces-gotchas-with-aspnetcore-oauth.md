---
title: GitHub Codespaces Gotchas with ASP.NET Core OAuth
date: 2021-08-15
tags: aspnet,aspnetcore,codespaces,dotnet,github,oauth
layout: post
description: "Some gotchas to account for when debugging an ASP.NET Core app using OAuth with GitHub Codespaces"
---

This week GitHub [Codespaces][1] was made [generally available][2] for Teams and
Enterprise, and coupled with the release of the ability to open any repository
in [Visual Studio Code][3] in a web browser [just by pressing `.`][4], I thought
I'd give it a try with some existing projects. In the process I hit a few
gotchas that took me a few hours to get to the bottom of. This post goes through
some of those and how to resolve them.

READMORE

One of the projects I decided I'd try it out on is the [sample application][5] I
recently published for integration testing with ASP.NET Core 6 [Minimal APIs][6].
This sample uses [GitHub OAuth][7] for authentication and is relatively simple
in its setup, with a mostly out-of-the box configuration.

Once I'd got the basic Codespaces setup added to the repository (which took me a
bit of time to bed-in and sort out), I found that whenever I tried to log in to
the sample application using the Codespaces preview URL when I ran it in the
debugger, I'd be greeted with an HTTP 405 error ([Method Not Allowed][14]).

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_http-405.png" alt="HTTP 405 error" title="HTTP 405 error">

However, the error page wasn't coming from my app. The `Server` HTTP response
header identified it as NGINX, and turning up the default log level to `Trace`
didn't show any requests hitting the app in the debugger for the `/signin` path
the form POST was sent to.

Weird. Confused, I posted [a question][8] on the GitHub Support Community and
left it for the rest of the day.

This weekend, I then went through some other repos to add Codespaces support to.
The setup was pretty much the same as with the first one I tried, yet any HTTP
POST resources those apps had in them worked fine.

Curious. What is it about this particular TodoApp that means that it gets an
HTTP 405 when trying to post to the `/signin` path?

After _quite_ a lot of trial and error, I found that `/signin` appears to be
some sort of reserved path that the GitHub Codespaces internals use. I haven't
been able to find any documentation about this anywhere about paths that you
cannot use, so I guess it's some sort of the internal management functionality
for their devs or something like that.

Once I changed the path in the app to `/sign-in`, I could hit the GitHub.com
OAuth page to grant permission to my app and get redirected back to complete
the OAuth dance to get logged in.

The next hurdle was that my OAuth callback path would 404. Seems that the NGINX
setup in Codespaces seems to also trap paths beginning with `/signin`. As the
out-of-the-box callback path was `/signin-github`, I needed to change that too.

With that sorted out, I had a third and final problem. GitHub would reject the
callback URL as invalid. Inspecting the logs in the debugger, I found that the
redirect URL being sent to GitHub was starting with `https://localhost/`. For
some reason the hostname for the Codespaces preview wasn't being used by the app.

At first I thought I'd just need to explicitly configure the ASP.NET Core [proxy
configuration for Linux][9], but that didn't seem to fix it either. Finally I
resorted to reading the source code for the `ForwardedHeadersMiddleware` class
in the ASP.NET Core repo and then I found [the answer][10].

By default, the `X-Forwarded-Host` HTTP request header is not inspected by the
middleware to update the value of the `Host` property on the `HttpRequest`.
Once I updated the configuration for the forwarded headers middleware, the OAuth
login flow for the application started to work.

I also subsequently found that the documentation on docs.microsoft.com is a bit
[out of date][11] and that there has been a config value [built-in since ASP.NET
Core 3.0][12] that will automatically configure the middleware for you if you set the
`ASPNETCORE_FORWARDEDHEADERS_ENABLED` setting to `true`.

This meant I could simplify the code to just two lines in the `Program.cs` file
to add on the additional flag for the middleware:

```
if (string.Equals(
        builder.Configuration["CODESPACES"],
        "true",
        StringComparison.OrdinalIgnoreCase))
{
    builder.Services.Configure<ForwardedHeadersOptions>(
        options => options.ForwardedHeaders |= ForwardedHeaders.XForwardedHost);
}
```

With that in place, I could just set up the forwarded headers from the VS Code
launch configuration:

```
"env": {
  "ASPNETCORE_FORWARDEDHEADERS_ENABLED": "${env:CODESPACES}"
}
```

Now the app works correctly when running in GitHub Codespaces! 🎉

You can see the full set of changes I made to get things working for the sample
app when running in Codespaces in [this Pull Request][13].

This was quite the head-scratcher to work out and fix, but I'm glad that I've
gotten things working in the end to give a nicer experience to people looking at
my sample repo with Codespaces without neeeding to clone it locally.

If you've stumbled across the blog post while trying to get this working
yourself, I hope you found reading this useful!

[1]: https://docs.github.com/codespaces "GitHub Codespaces Documentation"
[2]: https://github.blog/changelog/2021-08-11-codespaces-is-generally-available-for-team-and-enterprise/ "Codespaces is generally available for Team and Enterprise"
[3]: https://code.visualstudio.com/ "Visual Studio Code"
[4]: https://twitter.com/github/status/1425505817827151872?s=20 "New shortcut: Press . on any GitHub repo."
[5]: https://github.com/martincostello/dotnet-minimal-api-integration-testing "dotnet-minimal-api-integration-testing on GitHub.com"
[6]: https://devblogs.microsoft.com/aspnet/asp-net-core-updates-in-net-6-preview-4/#introducing-minimal-apis "Introducing minimal APIs"
[7]: https://www.nuget.org/packages/AspNet.Security.OAuth.GitHub/ "AspNet.Security.OAuth.GitHub on NuGet.org"
[8]: https://github.community/t/port-forwarding-for-http-post-not-working/195407 "Port Forwarding for HTTP POST not working"
[9]: https://docs.microsoft.com/en-us/aspnet/core/host-and-deploy/proxy-load-balancer#forward-the-scheme-for-linux-and-non-iis-reverse-proxies "Forward the scheme for Linux and non-IIS reverse proxies"
[10]: https://github.com/dotnet/aspnetcore/blob/bcfbd5cc47dde7f2be50a24721f24a020dc77356/src/Middleware/HttpOverrides/src/ForwardedHeadersMiddleware.cs#L191-L193 "ForwardedHeadersMiddleware source code on GitHub.com"
[11]: https://github.com/dotnet/AspNetCore.Docs/issues/18532#issuecomment-637092890 "Comment about out-of-date documentation"
[12]: https://devblogs.microsoft.com/aspnet/forwarded-headers-middleware-updates-in-net-core-3-0-preview-6/#configuration-only-wire-up-in-preview-6 "Configuration-only Wire-up in Preview 6"
[13]: https://github.com/martincostello/dotnet-minimal-api-integration-testing/pull/100/files
[14]: https://developer.mozilla.org/en-US/docs/Web/HTTP/Status/405 "405 Method Not Allowed"
