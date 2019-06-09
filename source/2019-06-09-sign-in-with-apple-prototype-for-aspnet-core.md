---
title: Prototyping Sign In with Apple for ASP.NET Core
date: 2019-06-09
tags: aspnetcore,dotnet,apple,sign in with apple
layout: bloglayout
description: "Prototyping an integration with ASP.NET Core for Sign In with Apple"
---

Last week at Apple's [WWDC 2019](https://developer.apple.com/wwdc19/ "WWDC19") conference, Apple announced a forthcoming service for enabling users to log into apps and services using their Apple ID, [_Sign In with Apple_](https://developer.apple.com/sign-in-with-apple/ "Sign In with Apple").

The main points of note about the new service are:

  * Users can sign in without having to give their email address to a third-party;
  * It will be required as an option in the future for apps that support third-party sign-in.

Just _one day_ after the announcement at WWDC19, [@leastprivilege](https://github.com/leastprivilege "@leastprivilege on GitHub.com") of IdentityServer fame, opened a [GitHub issue](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/issues/314 "Support for Apple Sign-in on GitHub.com") over at the [_AspNet.Security.OAuth.Providers_](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers "AspNet.Security.OAuth.Providers on GitHub.com") repository requesting a provider to support _Sign In with Apple_.

While the issue was opened slightly tongue-in-cheek, it's a valid start to the conversation about investigating support for this new technology (or not).

I've recently become a maintainer of the [aspnet-contrib](https://github.com/aspnet-contrib "aspnet-contrib org on GitHub.com") organisation in GitHub.com, which provides a suite of community-written providers for various OAuth 2.0 and Open ID 2.0 third-party authentication providers. Over the last few years I've made a number of contributions; for an [Amazon Login provider](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/157 "Add Amazon provider"), and most recently starting the work to [add support for ASP.NET Core 3.0](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/280 "Support ASP.NET Core 3.0").

Given the community discussion and appetite, some previous experience implementing [Apple Pay JS for ASP.NET Core](https://tech.just-eat.com/2016/10/10/bringing-apple-pay-to-the-web/ "Bringing Apple Pay to the web"), and some shiny new technology to play with, last I decided to try my hand at adding support for _Sign In with Apple_ for ASP.NET Core myself via _AspNet.Security.OAuth.Providers_.

READMORE

Others have already blogged in great detail about the _what_ of _Sign In with Apple_, including [Aaron Parecki](https://developer.okta.com/blog/2019/06/04/what-the-heck-is-sign-in-with-apple "What the Heck is Sign In with Apple?") of okta and [Bruno Krebs](https://auth0.com/blog/what-is-sign-in-with-apple-a-new-identity-provider/ "Sign In with Apple: Learn About the New Identity Provider") of auth0, so I won't reiterate the information they've covered there. Instead I'll focus on the ASP.NET Core integration specifics for how to get the service working with the REST API instead.

If you want to skip ahead, you can see the draft pull request for the _Sign In with Apple_ provider [here](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/318 "Sign in With Apple provider"), and a working demo you can try out for yourself here: [signinwithapple.azurewebsites.net](https://signinwithapple.azurewebsites.net/ "Sign In with Apple demo application")

## Integrating with Sign In with Apple

## Conclusion

## Links

  * [Sign In with Apple](https://developer.apple.com/sign-in-with-apple/ "Sign In with Apple - developer.apple.com")
  * [Sign In with Apple REST API](https://developer.apple.com/documentation/signinwithapplerestapi "Sign In with Apple REST API - developer.apple.com")
  * [_"What the Heck is Sign In with Apple?"_](https://developer.okta.com/blog/2019/06/04/what-the-heck-is-sign-in-with-apple "What the Heck is Sign In with Apple? - developer.okta.com")
  * [_"What is Sign In with Apple?_](https://auth0.com/blog/what-is-sign-in-with-apple-a-new-identity-provider/ "Sign In with Apple: Learn About the New Identity Provider - auth0.com")
  * [Sign In with Apple demo app](https://signinwithapple.azurewebsites.net/ "Sign In with Apple demo app - signinwithapple.azurewebsites.net")
  * [Sign In with Apple OAuth 2.0 provider for ASP.NET Core 2.x](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/318 "Sign in With Apple provider - github.com")
