---
title: Prototyping Sign In with Apple for ASP.NET Core
date: 2019-06-10
tags: aspnetcore,dotnet,apple,sign in with apple
layout: bloglayout
description: "Prototyping an integration with ASP.NET Core for Sign In with Apple"
---

Last week at Apple's [WWDC 2019](https://developer.apple.com/wwdc19/ "WWDC19") conference, Apple announced a forthcoming service for enabling users to log into apps and services using their Apple ID, [_Sign In with Apple_](https://developer.apple.com/sign-in-with-apple/ "Sign In with Apple").

The main points of note about the new service are:

- Users can sign in without having to give their email address to a third-party;
- It will be required as an option in the future for apps that support third-party sign-in.

Just _one day_ after the announcement at WWDC19, [@leastprivilege](https://github.com/leastprivilege "@leastprivilege on GitHub.com") of Identity Server fame, opened a [GitHub issue](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/issues/314 "Support for Apple Sign-in on GitHub.com") over at the [_AspNet.Security.OAuth.Providers_](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers "AspNet.Security.OAuth.Providers on GitHub.com") repository requesting a provider to support _Sign In with Apple_.

While the issue was opened slightly tongue-in-cheek, it's a valid start to the conversation about investigating support for this new technology (or not).

I've recently become a maintainer of the [aspnet-contrib](https://github.com/aspnet-contrib "aspnet-contrib org on GitHub.com") organisation in GitHub.com, which provides a suite of community-written providers for various OAuth 2.0 and Open ID 2.0 third-party authentication providers. Over the last few years I've made a number of contributions; for an [Amazon Login provider](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/157 "Add Amazon provider"), and most recently starting the work to [add support for ASP.NET Core 3.0](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/280 "Support ASP.NET Core 3.0").

Given the community discussion and appetite, some previous experience implementing [Apple Pay JS for ASP.NET Core](https://blog.martincostello.com/bringing-apple-pay-to-the-web "Bringing Apple Pay to the web"), and some shiny new technology to play with, last I decided to try my hand at adding support for _Sign In with Apple_ for ASP.NET Core myself via _AspNet.Security.OAuth.Providers_.

READMORE

Others have already blogged in great detail about the _what_ of _Sign In with Apple_, including [Aaron Parecki](https://developer.okta.com/blog/2019/06/04/what-the-heck-is-sign-in-with-apple "What the Heck is Sign In with Apple?") of okta and [Bruno Krebs](https://auth0.com/blog/what-is-sign-in-with-apple-a-new-identity-provider/ "Sign In with Apple: Learn About the New Identity Provider") of auth0, so I won't reiterate the information they've covered there. Instead I'll focus on the ASP.NET Core integration specifics for how to get the service working with the REST API instead.

If you want to skip ahead, you can see the draft pull request for the _Sign In with Apple_ provider [here](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/318 "Sign in With Apple provider"), and a working demo you can try out for yourself here: [signinwithapple.azurewebsites.net](https://signinwithapple.azurewebsites.net/ "Sign In with Apple demo application")

## Integrating with _Sign In with Apple_

### Boostrapping

The very first step was very easy, as that just required creating the project template to start coding the implementation into. In fact, _AspNet.Security.OAuth.Providers_ has a [Yeoman generator](https://github.com/aspnet-contrib/generator-aspnet-oauth "generator-aspnet-oauth on GitHub.com") which lets you bootstrap the scaffolding into the repo fairly quickly.

The latest version for ASP.NET Core 2.0 isn't available in [npmjs.org](https://www.npmjs.com/package/generator-aspnet-oauth "generator-aspnet-oauth on the npm registry") yet, but with a recent enough version of Yeoman you can run the generator directly from source as shown below.

```
> git clone https://github.com/aspnet-contrib/generator-aspnet-oauth.git
> git clone https://github.com/martincostello/AspNet.Security.OAuth.Providers.git
> cd AspNet.Security.OAuth.Providers\src
> git checkout -b Sign-In-With-Apple
> yo ..\..\generator-aspnet-oauth\generators\app\index.js

     _-----_     ╭──────────────────────────╮
    |       |    │   Welcome to the classy  │
    |--(o)--|    │  ASP.NET OAuth Provider  │
   `---------´   │        generator!        │
    ( _´U`_ )    ╰──────────────────────────╯
    /___A___\   /
     |  ~  |
   __'.___.'__
 ´   `  |° ´ Y `

? What is the name of the provider you want to create? Apple
? What is your name? Martin Costello
? What is the Authorization Endpoint for this service? https://appleid.apple.com/auth/authorize
? What is the Token Endpoint for this service? https://appleid.apple.com/auth/token
? What is the User Information Endpoint for this service? https://appleid.apple.com/auth/user
   create AspNet.Security.OAuth.Apple\AspNet.Security.OAuth.Apple.csproj
   create AspNet.Security.OAuth.Apple\AppleAuthenticationDefaults.cs
   create AspNet.Security.OAuth.Apple\AppleAuthenticationExtensions.cs
   create AspNet.Security.OAuth.Apple\AppleAuthenticationHandler.cs
   create AspNet.Security.OAuth.Apple\AppleAuthenticationOptions.cs
```

Here the URL of the User Information Endpoint was a guess, and I ended up deleting it for now as there isn't one documented.

With that done, I had a skeleton to start iterating on once I followed the [excellent guide](https://developer.okta.com/blog/2019/06/04/what-the-heck-is-sign-in-with-apple#how-sign-in-with-apple-works-hint-it-uses-oauth-and-oidc "How Sign In with Apple Works (Hint: it uses OAuth and OIDC)") Aaron Parecki put together which explains how to set up the various apps, services, certificates and keys you need to create in the Apple Developer website to get started.

To implement things, I set up a free Azure App Service website to quickly get something publicly hosted I could publish to from Visual Studio for rapid prototyping (don't judge me, it's faster for this use case), as well as to leverage the free HTTPS support which is required. This gave me a domain (`signinwithapple.azurewebsites.net`) to configure for the certificates and callback URL.

The _TL;DR_ of what you need is:

- An App ID (for your Services ID)
- A Services ID (for your _Client Id_)
- An _Apple Developer Domain Association_ file (or two) to verify your sign-in and email domain(s)
- A private key (to generate your _Client Secret_)
- Appropriate DNS `MX` record(s) in your DNS zone (for email relay, even if you don't intend to use it)

For the email relay I just verified my main .com domain as it already has MX records setup, plus I don't intend to actually leverage the email addresses from the signed-in users to send any emails.

With these all configured and downloaded, I had the values ready to try things out.

I deployed the [MVC sample app](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/tree/dev/samples/Mvc.Client "Mvc.Client sample app on GitHub.com") from _AspNet.Security.OAuth.Providers_ to the new Azure App Service slot to act as the testbed for the Apple provider.

### _"Where's the client secret?"_

Something different about _Sign In with Apple_ compared to most OAuth 2.0 based authentication providers is that Apple don't actually provide you with a client secret. Instead they provide you with a PKCS #8 (`.p8`) private key which you then use to generate a JSON Web Token (JWT) to use as the client secret. Apple requires that these have a validity period of no longer than 6 months, so you can't just generate a value as a one-off and use it forever more.

To start with I used Aaron's [`client-secret.rb`](https://github.com/aaronpk/sign-in-with-apple-example/blob/master/client-secret.rb "client-secret.rb") Ruby script from Windows Subsystem for Linux (WSL) to generate a client secret from my _Team ID_, _Key ID_, _Services ID_ (which  acts as your OAuth _Client ID_) and the `.p8` private key file.

This then let me check that the _basic_ integration flow worked and that I could get the Apple login ID back to the client application code.

With some logging, it also gave me some real values to use for further testing locally to iterate on getting the values from the `id_token` property in the [token response](https://developer.apple.com/documentation/signinwithapplerestapi/tokenresponse "TokenResponse object").

### ID Token Decoding

As [discussed](https://developer.apple.com/documentation/signinwithapplerestapi/generate_and_validate_tokens "Generate and validate tokens") in the Apple Developer documentation, the `id_token` value is a signed JWT value.

These can be [easily decoded](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/blob/b9a72d4928eff6bfe550e5f3abb3da3c81199090/src/AspNet.Security.OAuth.Apple/AppleAuthenticationHandler.cs#L110-L121 "") using the [System.IdentityModel.Tokens.Jwt](https://www.nuget.org/packages/System.IdentityModel.Tokens.Jwt/ "System.IdentityModel.Tokens.Jwt on NuGet.org") library:

```
// dotnet add System.IdentityModel.Tokens.Jwt

// Get the ID token from the OAuth token response
OAuthTokenResponse tokens = ...;
string token = tokens.Response.Value<string>("id_token");

// Parse the JWT
var tokenHandler = new JwtSecurityTokenHandler();
var userToken = tokenHandler.ReadJwtToken(token);

// Get the subject to use for the Name Identifier claim
string subject = userToken.Subject;
```

Without a _User Information Endpoint_ available, this is actually as much information as we can currently get about the user right now anyway!

### Token Validation

Also [discussed in the documentation](https://developer.apple.com/documentation/signinwithapplerestapi/fetch_apple_s_public_key_for_verifying_token_signature "Fetch Apple's public key for verifying token signature") is an endpoint for retrieving the Apple public key to validate the signature of the ID token.

Again, this is [relatively easy](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/blob/b9a72d4928eff6bfe550e5f3abb3da3c81199090/src/AspNet.Security.OAuth.Apple/Internal/DefaultAppleIdTokenValidator.cs#L40-L55) to do using the System.IdentityModel.Tokens.Jwt package.

```
// Get the ID token from the OAuth token response
OAuthTokenResponse tokens = ...;
string token = tokens.Response.Value<string>("id_token");

// Get the public keys from https://appleid.apple.com/auth/keys
string keysJson = await ...;

// Parse the keys
JsonWebKeySet keySet = JsonWebKeySet.Create(keysJson);

// Setup the validation parameters
var parameters = new TokenValidationParameters()
{
    ValidAudience = "{YOUR CLIENT ID}",
    ValidIssuer = "https://appleid.apple.com",
    IssuerSigningKeys = keySet.Keys,
};

// Validate the token - ValidateToken(...) throws an exception if it is invalid
var tokenHandler = new JwtSecurityTokenHandler();
tokenHandler.ValidateToken(context.IdToken, parameters, out var _);
```

In the prototype provider implementation the Apple public keys are cached on the assumption that they rotate infrequently to increase performance by removing the need to perform an additional HTTP call for each user log in.

This is something that I might make configurable before the final release, so it is up the integrator whether to always re-fetch the public keys, but in the prototype integrators can already provide their own [`AppleKeyStore`](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/blob/b9a72d4928eff6bfe550e5f3abb3da3c81199090/src/AspNet.Security.OAuth.Apple/AppleKeyStore.cs "AppleKeyStore class on GitHub.com") to change to implementation of how the public key is retrieved and stored.

### Generating the Client Secret in the app

With the basic end-to-end flow working using the Client Secret generated using the Ruby script completed, the next step in the implementation was to make it easier for the integrator by building in the ability to [dynamically generate it](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/blob/b9a72d4928eff6bfe550e5f3abb3da3c81199090/src/AspNet.Security.OAuth.Apple/AppleAuthenticationHandler.cs#L98-L108) from they various IDs and the private key.

This was the trickiest bit to get working (more on that a bit later), but again is mostly solved by the System.IdentityModel.Tokens.Jwt package.

```
// Generate a token valid for the maximum 6 months
var expiresAt = DateTime.UtcNow.Add(TimeSpan.FromSeconds(15777000));

var tokenDescriptor = new SecurityTokenDescriptor()
{
    Audience = "https://appleid.apple.com",
    Expires = expiresAt,
    Issuer = "{YOUR TEAM ID}",
    Subject = new ClaimsIdentity(new[] { new Claim("sub", "{YOUR CLIENT ID}") }),
};

// Load the .p8 file from disk, removing the
// `-----BEGIN PRIVATE KEY-----` and `-----END PRIVATE KEY-----`
// prefix and suffix, and joining `\n` characters between lines.
string content = await File.ReadAllTextAsync("AuthKey_{YOUR KEY ID}.p8");

string[] keyLines = content.Split('\n');
content = string.Join(string.Empty, keyLines.Skip(1).Take(keyLines.Length - 2));

byte[] privateKey = Convert.FromBase64String(content);

// Create an ECDSA 256 algorithm to sign the token
using (var privateKey = CngKey.Import(keyBlob, CngKeyBlobFormat.Pkcs8PrivateBlob))
using (var algorithm = new ECDsaCng(privateKey))
{
    algorithm.HashAlgorithm = CngAlgorithm.Sha256;

    var key = new ECDsaSecurityKey(algorithm) { KeyId = "{YOUR KEY ID}" };

    // Set the signing key for the token
    tokenDescriptor.SigningCredentials = new SigningCredentials(
        key,
        SecurityAlgorithms.EcdsaSha256Signature);

    // Create the token, which acts as the Client Secret
    var tokenHandler = new JwtSecurityTokenHandler();
    string clientSecret = tokenHandler.CreateEncodedJwt(tokenDescriptor);
}
```

Within the provider prototype, the generated Client Secret is cached until it expires, at which point it is re-generated. Integrators can customise this behaviour by providing their own implementation of [`AppleClientSecretGenerator`](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/blob/b9a72d4928eff6bfe550e5f3abb3da3c81199090/src/AspNet.Security.OAuth.Apple/AppleClientSecretGenerator.cs "AppleClientSecretGenerator class on GitHub.com").

### Cross-platform Support

Once the Client Secret was being generated successfully from the `.p8` file as-needed, I figured the bulk of the implementation was pretty much done.

As it turned out, I'd made some assumptions in the implementation from working on my Windows 10 laptop that meant that the provider only worked on Windows and not on Linux or [macOS](https://travis-ci.com/martincostello/AspNet.Security.OAuth.Providers/jobs/206552010#L968), which is a bit embarrassing for integrating with an Apple product, let alone on cross-platform .NET Core.

First this required changing the code so it could use more generic ECDA APIs to load the `.p8` private key from Linux and macOS. A bit of Google-fu later lead to me finding [this issue](https://github.com/dotnet/corefx/issues/18733 "why CngKey.Import is not supported on ubuntu?") in Core CLR. It turns out that PKCS #8 keys aren't supported in .NET Core 2.x on non-Windows platforms. This is [fixed in .NET Core 3.0](https://github.com/dotnet/corefx/pull/30271 "Add support for importing/exporting asymmetric key formats"), but that doesn't do us any good right now.

To fix this I followed [these instructions](https://github.com/dotnet/corefx/issues/18733#issuecomment-296723615) to generate a `.pfx` file (PKCS #12) from the `.p8` file and then use the `X509Certificate2` class to load the key instead ([commit](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/318/commits/0683f718719bf7f225e954c9eec57f7c909b7361 "Fix Linux and macOS secret generation")) on non-Windows platforms. It's a bit bleurgh to have to branch the code based on the operating system _and_ require a different key format, but without pulling in _a lot_ of extra code I didn't think it was worth it.

Unfortunately, that didn't fix everything either. It fixed Linux but [macOS was still broken](https://travis-ci.com/martincostello/AspNet.Security.OAuth.Providers/jobs/206584185#L968). This time it was because of [this issue](https://github.com/dotnet/corefx/issues/24225 "X509Certificate2/CommonCrypto: Unable to open PKCS#12 files with no password and valid MAC") where macOS cannot open private keys with no password set.

This required me to add a [further option](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/318/commits/a088f736c2ebc2314f93195cd61f14aadb94657) to support specifying a password for the certificate, which on reflection I should have done anyway. I was just being lazy in my tests.

With that change done, finally everything was working as expected on both Windows, Linux and macOS!

I merged the provider prototype changes to the ASP.NET Core 3.0 preview 5 branch of _AspNet.Security.OAuth.Providers_ and took a look at the new APIs added to see if it could remove the need to fork the code. In fact [it does neatly](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/commit/ee4a2f7ca3d0f1550d3f3990a63e9fb01158caf9 "Support PKCS 8 keys on macOS and Linux"), meaning that the ASP.NET Core 3.0 version of the provider would be able to work exactly the same using just the `.p8` private key without the need for a password option on all three operating systems.

```
// Load the .p8 file from disk, removing the
// `-----BEGIN PRIVATE KEY-----` and `-----END PRIVATE KEY-----`
// prefix and suffix, and joining `\n` characters between lines.
byte[] privateKey = ...;

// Create an ECDSA 256 algorithm to sign the token
using var algorithm = ECDsa.Create();
algorithm.ImportPkcs8PrivateKey(privateKey, out int _);

var key = new ECDsaSecurityKey(algorithm) { KeyId = "{YOUR KEY ID}" };

// Generate the token...
```

### Putting it all together

With the provider prototype fully functional, the only code required to add _Sign In with Apple_ support to an existing ASP.NET Core 2.x (or 3.0 preview) application can be as little as:

```
.AddApple(options =>
{
    options.ClientId = Configuration["AppleClientId"];
    options.KeyId = Configuration["AppleKeyId"];
    options.TeamId = Configuration["AppleTeamId"];

    options.UsePrivateKey(
        (keyId) =>
            HostingEnvironment.ContentRootFileProvider.GetFileInfo($"AuthKey_{keyId}.p8"));
})
```

One last _"gotcha"_: if you're using Azure App Service, you must set the `WEBSITE_LOAD_USER_PROFILE` application setting for your deployment slot to a value of `1`; otherwise, the application will not be able to load the private key from your `.p8` file.

## Conclusion

So after a weekend's work, I think I've gotten a fairly nice prototype working that makes _Sign In with Apple_ easy to integrate into an existing ASP.NET Core 2.x application based on the currently available functionality and documentation.

There's still a few rough edges, such as the disparate private key support between Windows (PKCS #8) and Linux/macOS (PKCS #12), and the lack of an ability to _actually_ get the signed-in user's name and email address. Otherwise things work and should be easy to tweak and build upon for later beta releases of the service between now and when it becomes generally available to Apple users later in 2019.

If you've got any feedback on the provider prototype, feel free to leave a comment on the [Pull Request](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/318 "Sign in With Apple provider Pull Request") over on GitHub!

## Links

- [Sign In with Apple](https://developer.apple.com/sign-in-with-apple/ "Sign In with Apple - developer.apple.com")
- [Sign In with Apple REST API](https://developer.apple.com/documentation/signinwithapplerestapi "Sign In with Apple REST API - developer.apple.com")
- [_"What the Heck is Sign In with Apple?"_](https://developer.okta.com/blog/2019/06/04/what-the-heck-is-sign-in-with-apple "What the Heck is Sign In with Apple? - developer.okta.com")
- [_"What is Sign In with Apple?_](https://auth0.com/blog/what-is-sign-in-with-apple-a-new-identity-provider/ "Sign In with Apple: Learn About the New Identity Provider - auth0.com")
- [Sign In with Apple demo app](https://signinwithapple.azurewebsites.net/ "Sign In with Apple demo app - signinwithapple.azurewebsites.net")
- [Sign In with Apple OAuth 2.0 provider for ASP.NET Core 2.x](https://github.com/aspnet-contrib/AspNet.Security.OAuth.Providers/pull/318 "Sign in With Apple provider - github.com")
