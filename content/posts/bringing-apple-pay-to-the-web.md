---
title: Bringing Apple Pay to the web
date: 2016-10-10
tags: apple pay,apple pay js,javascript,asp.net core
layout: post
description: "How Just Eat implemented the Apple Pay JS API into the just-eat.co.uk website."
image: "https://cdn.martincostello.com/blog_apple-pay-hero.png"
---

> _This blog post was originally published by me on the [Just Eat Tech blog][original-post]._

## Introduction

Back in June at [WWDC][annoucement], Apple announced that [Apple Pay][apple-pay] was expanding its reach.
No longer just for apps and Wallet on TouchID compatible iOS devices and the Apple Watch, it would also
be coming to Safari in iOS 10 and macOS Sierra in September 2016.

Just Eat was a launch partner when Apple Pay was released in the UK in our [iOS app][just-eat-ios] in 2015.
We wanted to again be one of the first websites to support Apple Pay on the web by making it available within
[just-eat.co.uk][just-eat-website]. Our mission is to make food discovery exciting for everyone – and supporting
Apple Pay for payment will make your experience even more dynamic and friction-free.

Alberto from our iOS team wrote a post about [how we introduced Apple Pay][just-eat-apple-pay] into our
iOS app last year, and this post follows on from that journey with a write-up of how we went about making
Apple Pay available on our website to iOS and macOS users with the new [Apple Pay JS SDK][apple-pay-js].

<!--more-->

## Getting set up

In the iOS world, due to the App Store review process and signed entitlements, once your app is in users'
hands you just use [PassKit][pass-kit] to get coding to accept payments. For the web things are a little different.

Due to the more loosely-coupled nature of the integration, instead trust between the merchant (Just Eat in our case)
and Apple is provided through some additional means:

- A valid SSL/TLS certificate
- A validated domain name to prove a merchant owns a given domain
- A Merchant Identify Certificate

As we already use Apple Pay here at Just Eat, the first few steps for getting up and running have already been
achieved. We already have an Apple developer account and a merchant identifier via our iOS app development, and
the Just Eat website is already served over HTTPS, so we have a valid SSL/TLS certificate.

We also do not need to worry about decrypting Apple Pay payment tokens ourselves. We use a third-party payment
provider to offload our payment processing, and internal APIs for passing an Apple Pay token for processing via
our payment provider already exists for handling iOS payments, so the website can integrate with those as well.

To get up and running and coding end-to-end, we need just need a Merchant Identity Certificate. This is used to
perform two-way TLS authentication between our servers and the Apple Pay servers to validate the merchant session
when the Apple Pay sheet is first displayed on a device.

The first step in getting a Merchant Identify Certificate is to validate a domain. This involves entering a domain
name into the Apple Pay Developer Portal for the merchant identifier you want to set up Apple Pay on the web
for – where you then get a file to download. This is just a text file that verifies the association between your
domain and your merchant ID. You just need to deploy this file to the web server(s) hosting your domain so Apple
can perform a one-time request to verify that the file can be found at your domain.

You need to do this for all domains you wish to use Apple Pay for, including internal ones for testing, so you may
have to white list the Apple IP addresses so that the validation succeeds.

Once you have validated at least one domain, you can generate your Merchant Identify Certificate for your Merchant
Identifier. This requires providing a Certificate Signing Request (CSR).

Uploading the CSR file in the Apple Developer Portal will generate a certificate file (`merchant_id.cer`) for you to
download. This acts as the _public_ key for your Merchant Identify Certificate. The _private_ key is the CSR you provided.
In order to create a valid TLS connection to the Apple Pay merchant validation server, you will need to create a
public-private key pair using the CSR and the CER files, such as using a tool like OpenSSL. In our case we generated
a [.pfx][pfx] file for use with .NET. Make sure you keep this file secure on your server and don’t expose it to your
client-side code.

## Separating concerns

So now we’ve got a validated domain and a Merchant Identify Certificate, we can start thinking about implementing the
JavaScript SDK. At a high-level the components needed to create a working Apple Pay implementation in Safari are:

1. JavaScript to test for the presence of Apple Pay, display the Apple Pay sheet and to respond to user interactions and receive the payment token
1. CSS to render the Apple Pay button on a page
1. An HTTPS resource to perform merchant validation

From the user’s point of view though, it’s just a button. So rather than add all the code for handling Apple Pay
transactions directly into the codebase of our website, we decided instead to contain as much of the implementation
as possible in a separate service. This service presents its own API surface to our website, abstracting the detail
of the Apple Pay JavaScript SDK itself away.

The high-level implementation from the website’s point of view is therefore like this:

1. Render a hidden div on the appropriate page in the checkout flow to represent the Apple Pay button as well as some meta and link tags to drive our JavaScript API
1. Reference a JavaScript file from the Apple Pay service via a script tag
1. Provide some minimal CSS to make the Apple Pay button size and colour appropriate to the current page
1. Call a function on our JavaScript API to test for whether Apple Pay is available
1. If it is, call a second function passing in some parameters related to the current checkout page, such as the user’s basket, the DOM element for the div representing the Apple Pay button and some callback functions for when the payment is authorised, fails or an error occurs.

The rest of the Apple Pay implementation is handled by our JavaScript abstraction so that the Just Eat website itself
never directly calls the Apple Pay JavaScript functions.

Our new Apple Pay service itself should have the following responsibilities:

- Serve the JavaScript file for the abstraction for the website
- Serve a file containing the [base CSS][apple-pay-css] for styling the Apple Pay button
- Provide HTTP resources that support Cross Origin Resource Sharing ([CORS][cors]) to:
  1. Provide the payment request properties to set up an Apple Pay sheet
  1. Validate merchant sessions
  1. Verify that a restaurant partner delivers to the selected delivery address
  1. Receive the Apple Pay payment token to capture funds from the user and place their order

Separating the CSS, JavaScript and back-end implementation allows us to decouple the implementation from our website
itself allowing for more discrete changes. For example, the current Apple Pay version is _1_. By abstracting things
away we could make changes to support a future version _2_ transparently from the website’s point-of-view.

## Delving into the implementation

As mentioned in the high-level design above, integrating Apple Pay into a website requires a mix of client-side
and server-side implementation. We need to implement some JavaScript, make some CSS available and provide some
server-side HTTP resources to handle merchant validation of payment processing. There’s also some HTTP meta and
link tags you can add to enhance your integration.

Let’s delve into the different layers and things we need to add...

### HTML

Well first we need an Apple Pay button. You can add one with some HTML like this:

```html
<div class="hide apple-pay-button apple-pay-button-black" />
```

Ignore the `apple-pay-*` CSS classes for now as I’ll come back to them, but the `hide` class (or some other similar
approach) ensures that the div for the button is not visible when the page first loads. This allows us to display
it as appropriate once we have detected that Apple Pay is available in the browser using JavaScript.

### HTML metadata

Apple Pay supports a number of different HTML `meta` and `link` tags that you can use to improve the user
experience for your integration.

First, there’s some `link` tags you can add to provide an icon for use on an iPhone or iPad when a confirmation
message is shown to the user initiating a payment from macOS:

```html
<link rel="apple-touch-icon" href="https://dy3erx8o0a6nh.cloudfront.net/images/touch-icon-120.png" sizes="120x120" />
<link rel="apple-touch-icon" href="https://dy3erx8o0a6nh.cloudfront.net/images/touch-icon-152.png" sizes="152x152" />
<link rel="apple-touch-icon" href="https://dy3erx8o0a6nh.cloudfront.net/images/touch-icon-180.png" sizes="180x180" />
```

These link elements can even be added dynamically by scripts when you detect the Apple Pay is available, provided
that they are in the DOM before you create an `ApplePaySession` object.

There’s also some meta tags you can add so that crawlers (such as [Googlebot][googlebot]) can identify your website
as supporting payment through Apple Pay:

```html
<meta property="product:payment_method" content="ApplePay" />
<meta name="payment-country-code" content="GB" />
<meta name="payment-currency-code" content="GBP" />
```

### Integrating the Apple Pay JavaScript SDK

So now we’ve got the HTML for the Apple Pay button and some metadata tags, we need some JavaScript to drive the integration.

In our case we have placed all of our Apple Pay-related JavaScript into a single file. This allows us to use server-side
feature flags to decide to render the script tag for it (or not), so that the relevant file is only fetched when the
feature is enabled.

Within this JavaScript file, there are functions for dealing with the Apple Pay workflow and calling the Safari
functions in the browser.

The psuedo-code for an implementation within a consuming website would be:

```javascript
// Determine if Apple Pay is supported by the current device
if (je.applePay.isSupportedForCheckout() === true) {

  // Create a configuration object from the page using
  // conventions to detect controls like the button.
  var config = je.applePay.Controller.createConfig();

  // Register a callback function to invoke when the
  // payment is successfully authorised. In our case
  // the response contains the user's order Id.
  config.onPaymentAuthorized = function (response) {
    if (response.orderId) {
      // Do something with the order Id
    }
  };

  // Create a controller to handle the Apple Pay workflow such as
  // the event handler for when the Apple Pay button is clicked.
  var controller = new je.applePay.Controller(config);

  // Call the function that determines if Apple Pay is available
  // for use in the current window location (e.g. https://www.just-eat.co.uk)
  // and displays the Apple Pay button to the user if it is.
  controller.displayIfAvailable();
}
```

First we have functions in `je.applePay` that contain simple functions for feature detection. For example,
the `isSupportedByDevice()` function tests if the current browser supports Apple Pay at all, whereas
the `isSupportedForCheckout()` function additionally tests if the Just Eat specific information (such as the
ID of the basket to pay for) is available to the current page.

The controller is the top-level object in our abstraction that the containing page uses to handle the Apple
Pay payment flow. This handles things so that when the user clicks the Apple Pay button, we create an Apple
Pay session with the appropriate payment information, do callbacks to the server to validate the merchant
session and capture payment – and invoke the website-supplied callback functions when the payment process ends.

Within our abstraction, we use the `ApplePaySession` object to drive our integration. For example, to test
for Apple Pay support, we use code similar to this (logging removed for brevity):

```javascript
/**
 * Returns whether Apple Pay is supported on the current device.
 * @returns {Boolean} Whether Apple Pay is supported on the current device.
 */
je.applePay.isSupportedByDevice = function () {

    var isSupported = false;
    var isApplePaySessionInWindow = "ApplePaySession" in window && ApplePaySession;

    if (isApplePaySessionInWindow) {
        var canMakePayments = ApplePaySession.canMakePayments() === true;
        var supportsVersion = ApplePaySession.supportsVersion(1) === true;
        isSupported = canMakePayments && supportsVersion;
    }

    return isSupported;
};
```

Assuming that the device supports Apple Pay then we’ll want to display the Apple Pay button. However
before we do that we’ll need to wire-up an onclick event handler to invoke the JavaScript to handle
the payment process itself when it is clicked or pressed. For example with jQuery:

```javascript
// Get the button
var button = $(".apple-pay-button");

// Register the event handler
button.on("click", function (e) {
  // Apple Pay implementation
});

// Show the button now it is ready for use
button.removeClass("hide");
```

Now the Apple Pay button will be displayed. The rendering of the button itself is handled by the [CSS][apple-pay-css]
provided by Apple. There are four possible variants. First there’s a choice between a black or a white
button, then there’s the choice of either an Apple Pay logo only, or the logo prefixed by “By with” (CSS).

The logo itself is provided by resources built into Safari, such as shown in this snippet:

```css
.apple-pay-button-black {
  background-image: -webkit-named-image(apple-pay-logo-white);
  background-color: black;
}
```

The CSS file for this is loaded dynamically by our JavaScript abstraction so users with devices that do not support
Apple Pay do not pay the penalty of a network request to get the CSS file. This also removes the need for the
consuming website to explicitly load the CSS itself with a `link` tag and allows the location of the CSS file
itself to be modified at any time in our Apple Pay service.

So when the user either taps or clicks the button, that’s when the work to start the Apple Pay session begins.
First you need to create a properly set up [payment request][apple-pay-paymentrequest] object to create an instance
of [`ApplePaySession`][apple-pay-applepaysession] along with the Apple Pay version (currently `1`).

Be careful here – Apple Pay only allows an `ApplePaySession` object to be created when invoked as part of a
user gesture. So, if you want to do any interaction with your server-side implementation here, ensure you do
not make use of asynchronous code such as with a `Promise` object. Otherwise creating the `ApplePaySession` may
occur outside the scope of the gesture handler, which will cause a JavaScript exception to be thrown and the
session creation to fail.

We haven’t done enough to show the sheet yet though. Next we need to register the callback functions for the
events we want to receive callbacks for. At a minimum you will need two of these:

- [`onvalidatemerchant`][apple-pay-onvalidatemerchant]
- [`onpaymentauthorized`][apple-pay-onpaymentauthorized]

`onvalidatemerchant` is called after the sheet is displayed to the user. It provides you with a URL to pass
to the server-side of your implementation to validate the merchant session.

An example of how you could do this in jQuery is shown in the snippet below:

```javascript
session.onvalidatemerchant = function (event) {
  var data = {
    validationUrl: event.validationURL
  };
  $.post("/your-validation-resource-url", data).then(function (merchantSession) {
    session.completeMerchantValidation(merchantSession);
  });
};
```

`onpaymentauthorized` is called after payment is authorised by the user either with a fingerprint from an
iPhone or iPad or by pressing a button on their Apple Watch. This provides the payment token for capturing
the funds from the user.

An example of how you could do this in jQuery is shown in the snippet below:

```javascript
session.onpaymentauthorized = function (event) {

  var data = {
    billingContact: event.payment.billingContact,
    shippingContact: event.payment.shippingContact,
    token: event.payment.token.paymentData
  };

  $.post("/your-payment-processing-url", data).then(function (response) {
    session.completePayment(
      response.successful ?
      ApplePaySession.STATUS_SUCCESS :
      ApplePaySession.STATUS_FAILURE);
  });
};
```

The functionality to actually capture funds from the user is outside the scope of this blog post –
information about decrypting Apple Pay payment tokens can be found [here][payment-token].

There’s also events for payment selection, shipping method selection, shipping contact selection and
cancellation. This allows you to do things such as:

- Dynamically adjust pricing based on payment method or shipping address
- Validate that the shipping address is valid, for example whether a restaurant delivers to the specified shipping address

Note that before the payment is authorised by the user, not all of the shipping contact and billing
contact information is yet available to you via the parameters passed to the event handlers. For
example, the country, locality (eg a city or town), administrative area (e.g. a county or state) and the
first part of the postal code (eg outward code in the UK, such as _EC4M 7RF_). This is for privacy reasons
as before the user authorises the payment it is still a request for payment, and as such the full information
is only revealed to use you the integrator by the `onpaymentauthorized` event.

Once you’ve registered all your event handlers, you just need to call the [`begin`][apple-pay-begin] function
to display the Apple Pay sheet.

### HTTP resources

Our server-side implementation has 4 main resources that we consume from our JavaScript code for all flows:

1. `GET /applepay/metadata`
1. `GET /applepay/basket/{id}`
1. `POST /applepay/validate`
1. `POST /applepay/payment`

The `metadata` resource is used to test whether Apple Pay is available on the current domain (for example
`www.just-eat.co.uk`). The JSON response returned indicates whether the Apple Pay feature is enabled for the
referring domain, the merchant capabilities, the supported payment networks, the country and currency code and
the available Apple Pay touch icons and their URIs. This allows our JavaScript example to build up the `link`
tags for the touch icons dynamically, deferring the need for them until necessary.

The `basket` resource is used to fetch details about the user’s current basket so that we can render the
Apple Pay sheet to show the items for their order, the total, the shipping method and the required shipping
contact fields. For example, we require the user’s postal address for delivery orders but that isn’t required
for collection orders. This removes the need for the JavaScript to determine any of this information itself, as
it can just copy the fields into the payment request object for the `ApplePaySession` constructor directly
from the JSON response.

The `validate` resource is used to implement the merchant session validation with the Apple Pay servers.
This posts the Apple validation URL to our back-end which then calls the specified URL using the Merchant
Identify Certificate associated with the requesting domain to validate the merchant session. The JSON response
then returns a  MerchantSession  dictionary for consumption by the JavaScript to pass to the
[`completeMerchantValidation` function][apple-pay-completemerchantvalidation].

The `payment` resource is used to POST the encrypted payment token, as well as the basket ID and billing and
shipping contact details to our server to place the order. This resource then returns either an order ID
(and optionally a token if a guest user account was created) if the payment was authorised successful or an
error code otherwise.

For delivery orders we also have a `POST /applepay/basket/{id}/validatepostalcode` resource to check that
the user’s chosen shipping address can be delivered to.

### Merchant Validation

Initiating the POST to Apple’s servers to validate the session is relatively simple in ASP.NET Core
(more about that later), provided you’ve already performed the steps to create a .pfx file for your
Merchant Identify Certificate.

First we need to load the certificate, whether that’s from the certificate store or from a file on disk.
In our service we store the certificate as an embedded resource as we have multiple certificates for
different environments, but the simplest form is loading from disk.

```csharp
var certificate = new X509Certificate2(
    "merchant_id.pfx",
    "MySuperSecretPa$$w0rd");
```

This was the approach I was using in some local initial testing, but when I deployed the code to a
[Microsoft Azure App Service][azure-app-service] to leverage the free SSL certificate, this stopped
working. After some digging around I found that this was because on Windows you need to be able to
load the user profile to access private keys in certificates, and this isn’t possible by default in
IIS as it isn’t loaded. This is easy enough to fix when you have full control of the infrastructure
(such as our [Amazon Web Services (AWS) Elastic Cloud Compute (EC2)][aws-ec2] instances), but there’s
no option available to enable this in Azure.

Luckily there is a way around this. First, you upload the certificate that has a private key that you
wish to use to the App Service using the _SSL certificates_ tab in the [Azure Portal][azure-portal].
Next, you add the `WEBSITE_LOAD_CERTIFICATES` App setting to the _Application settings_ tab and set its
value to the thumbprint of the certificate you want to use. This causes the App Service to make the
specified certificate available in the _My_ store in the _Current User_ location so it can be read by
the identity associated with the IIS App Pool. Note that the `validOnly` parameter value is set to `false`;
if it is not the Merchant Identifier Certificate will not be loaded as it is not considered valid for
use by Windows, even though it is valid from Apple’s perspective.

```csharp
using (var store = new X509Store(StoreName.My, StoreLocation.CurrentUser))
{
    store.Open(OpenFlags.ReadOnly);

    var certificates = store.Certificates.Find(
        X509FindType.FindByThumbprint,
        "MyCertificateThumbprint",
        validOnly: false);

    var certificate = certificates[0];
}
```

The next step in the merchant validation process is to construct the payload to `POST` to the Apple server.
For this we need our domain name, the store display name (in our case "Just Eat") and the merchant identifier.
While we could configure the merchant identifier to use per domain, we can be smart about it and read it
from the Merchant Identifier Certificate instead. Thanks to [Tom Dale’s node.js][apple-pay-merchant-session-server]
example implementation, we discovered that this can be found from the `1.2.840.113635.100.6.32` X.509 extension
field, so we can read it out of our `X509Certificate2` like so:

```csharp
var extension = certificate.Extensions["1.2.840.113635.100.6.32"];
var merchantId = System.Text.Encoding.ASCII.GetString(extension.RawData).Substring(2);
```

Now we can `POST` to the validation URL we received from the JavaScript. As mentioned previously we need
to provide the Merchant Identifier Certificate with the request for two-way TLS authentication. This is
achieved by using the `HttpClientHandler` class which provides a `ClientCertificates` property where we
can use our certificate, and then pass it into the constructor of `HttpClient` to handle authentication
for use when we POST the data:

```csharp
var payload = new
{
    merchantIdentifier = merchantId,
    domainName = "www.just-eat.co.uk"
    displayName = "Just Eat"
};

var handler = new HttpClientHandler();
handler.ClientCertificates.Add(certificate);

var httpClient = new HttpClient(handler, disposeHandler: true);

var jsonPayload = JsonConvert.SerializeObject(payload);
var content = new StringContent(jsonPayload, Encoding.UTF8, "application/json");

var response = await httpClient.PostAsync(requestUri, content);
response.EnsureSuccessStatusCode();
```

Assuming we get a valid response from the Apple server, then we just need to deserialise the JSON
containing the merchant session and return it to the client from our API controller method:

```csharp
var merchantSessionJson = await response.Content.ReadAsStringAsync();
var merchantSession = JObject.Parse(merchantSessionJson);

return Json(merchantSession);
```

Now our JavaScript needs to consume the response body as mentioned earlier in the JavaScript implementation
to pass it to the [`ApplePaySession.completeMerchantValidation`][apple-pay-completemerchantvalidation] function
to allow the user to authorise the payment.

## New Tricks with ASP.NET Core

When we started implementing Apple Pay for our website, [ASP.NET Core][aspnet-core] 1.0.0 had just been
released, and as such we were running all our C#-based code on the full .NET Framework. We decided that
given the relatively small size and self-contained nature of the service for Apple Pay (plus there being
no legacy code to worry about) that we’d dip our toes into the new world of ASP.NET Core for implementing
the service for Apple Pay.

There are a number of capabilities and enhancements of ASP.NET Core that made it attractive for the
implementation, but the main one was the improved integration with client-side focused technologies, such
as [Bower][bower], [Gulp][gulp] and [npm][npm]. Given that a bulk of the implementation is in JavaScript, this
made it easier to use best-practice tools for JavaScript (and CSS) that provide features such as
concatenation, minification, linting and testing. This made implementing the JavaScript part of the integration
much easier to implement that the equivalent workflow in an ASP.NET MVC project in Visual Studio.

### Getting cut at the bleeding edge

Of course, going with a new version of a well-established technology isn’t all plain-sailing. There’s been
a few trade-offs moving to ASP.NET Core that have made us go back a few steps in some areas. These are gaps
we hope to address in the near future to obtain feature parity with our existing ASP.NET applications. Some
of these trade-offs are detailed below.

#### Dependencies

Here at Just Eat we have a variety of shared libraries that we add as dependencies into our .NET applications
to share a common best-practice and allow services to focus on their primary purpose, rather than also have
to worry about boiler-plate code, such as for [logging][nlog-structured-logging], [monitoring][statsd-nuget]
and communicating with other Just Eat services over HTTP.

Unfortunately a number of these dependencies are not quite in the position to support consumption from
.NET Core-based applications. In most cases this is due to dependencies we consume ourselves not supporting
.NET Core (such as [Autofixture][autofixture] used in tests), or using .NET APIs that are not present in .NET
Core’s surface area (such as changes to the `UdpClient` class).

We’re planning to move such libraries over to support .NET Core in due course
([example][nlog-structured-logging-issue]), but the structure of the dependencies makes this a non-trivial task.
The plan is to move our Apple Pay service over to versions of our libraries supporting .NET Core as they become
available, for now it uses its own .NET Core forks of these libraries.

#### Monitoring

At Just Eat we have a very mature monitoring and logging solutions using [Kibana][kibana] and
[Grafana][grafana], amongst other tools. Part of our monitoring solution involves a custom service that is
installed on our AWS EC2 Amazon Machine Images (AMIs) which collects performance counter data to publish
to [StatsD][statsd].

Unfortunately ASP.NET Core [does not currently implement performance counters][aspnet-core-perf-counters] on
Windows. In ASP.NET, there are [various performance counters available][aspnet-perf-counters] that we collect
as part of our monitoring, such as the number of current IIS connections, request execution times, etc. Even
though ASP.NET Core can be hosted via IIS, because the .NET Framework is not used, these performance counters
are of no use when it comes to monitoring an ASP.NET Core application.

### Testing the implementation

So once we’ve gotten our server-side implementation to get details for rendering the Apple Pay sheet, validating
merchant sessions and processing payment in place, as well as our JavaScript abstraction and base CSS, we can
start going about testing it out.

But how do we test Apple Pay without using our own personal credit/debit card?

Luckily with iOS 10, watchOS 3 and macOS Sierra, Apple have provided us with a way to do this. It’s called the
[Apple Pay Sandbox][apple-pay-sandbox]. This provides us with a way to set up users with “real” payment cards
that allow us to test transaction processing (at least up to the point of trying to capture funds). You can
find more details on the website, but the main steps are:

1. Setup a sandbox tester account in iTunes Connect
1. Sign into iCloud on your test device(s) using your sandbox tester
1. Add one or more test card(s) to Wallet on you test device(s)

Using the Apple Pay sandbox then allows you to test as many transactions as you like on your test devices without
worrying about spending a fortune or misplacing your personal payment card details.

#### Stubbing Out the SDK

With the majority of Just Eat’s back-end services (and our website) being written in ASP.NET, this posed a bit
of a challenge for testing. Of course the interactions with the sheet and the rendering need to be tested on a
real Apple Pay-supporting device, but how could we run the full-back end stack on our local Windows 10 machines
and use Apple Pay for local testing of changes without setting up lots of proxying to macOS and iOS test devices?

Well luckily in JavaScript it’s quite simple to add a [polyfill][polyfill] to a browser to provide a native API
where there would otherwise not be one available. So that’s what we did.

You can find it in a [here][apple-pay-js-polyfill] on GitHub.

Effectively the polyfill provides the `ApplePaySession` object if it does not already exist, and functions
in a way that makes the functions behave as if Apple Pay is available on the current device and chains the
events and their handlers together to make it appear that a user is interacting with the Apple Pay sheet.

Of course it is no substitute for testing with a real device, but the polyfill provides enough of an
implementation to test feature detection (i.e. only adding the button if Apple Pay is supported) and the
server side implementation for fetching and rendering the basket, performing merchant validation, and
passing on a valid sandbox payment token.

You can get a valid payment token for a sandbox transaction that you can embed within your own copy of the
Polyfill by adding some JavaScript logging to print out the text representation of the object passed as the
event parameter to the [`onpaymentauthorized`][apple-pay-onpaymentauthorized] function, as well as populating
it with some appropriate billing and payment contact details.

We use the polyfill for testing in our QA environments by loading it into the browser via a `script` tag in
our checkout-related pages where the Apple Pay button would appear.

### Deployment

So we’ve got our new service, and we’ve integrated it into our website and it’s all working locally.
Now it just needs deploying to our QA environments for testing, and then eventually onto our production environment.

We have our own deployment pipeline here at Just Eat that sets up deploying IIS applications from ZIP packages
and we also build our own custom AWS AMIs to deploy our services onto, so that’s all taken care of by our
Platform Engineering team.

Our AMIs do not yet have .NET Core installed on them though, so if we tried to use the deployed in IIS
it would return an HTTP 502. That’s easy enough to resolve though, we just need to make a new AMI with .NET Core on it.

This is nice and easy as [Chocolatey][chocolatey] provides packages for both the .NET Core runtime and the
Windows Server Hosting installer for IIS hosting.

Now there’s just a few more things we need to do to get our feature ready to run:

1. We need to set the `ASPNETCORE_ENVIRONMENT` environment variable so that the application runs with the right configuration
1. We need to set up the registry hives required for the ASP.NET Core [data protection system][aspnet-core-data-protection] (used for things like antiforgery tokens)
1. We need to adjust the App Pool configuration

Our deployment process already provides us with hooks to run PowerShell scripts post-deployment, so we just
need to write some small scripts to do the steps.

#### Setting the environment name

We can set the environment name machine-wide because we deploy each service on its own EC2 instance.
There are other approaches available, like setting environment variables in the ASP.NET Core Module, but this was simpler:

```powershell
[Environment]::SetEnvironmentVariable("ASPNETCORE_ENVIRONMENT", $environmentName, [System.EnvironmentVariableTarget]::Machine)
```

#### Configuring the App Pool

We also need to amend the IIS App Pool for the website to disable the .NET Framework (because we don’t need it)
and to load the user profile so we can load the private keys in our Merchant Identifier Certificates.

```powershell
$appCmd = [IO.Path]::Combine($env:WinDir, "System32", "inetsrv", "appcmd")

# Set the site to run no managed code
& $appCmd set apppool "/apppool.name:$siteName" "/managedRuntimeVersion:"

# Load the user profile so X509 certificate private keys can be loaded
& $appCmd set config -section:applicationPools "/[name='$siteName'].processModel.loadUserProfile:true"
```

#### Setting Up Data Protection

The process for setting up Data Protection for IIS, which in turn provides a link to a PowerShell
script, can be found [here][aspnet-core-iis].

After these three steps are done, then IIS just needs to be restarted (such as with `iisreset`) to
pick up the configuration changes.

## The (Apple) pay off

So now with Apple Pay integrated into our website, it’s possible for the user to pay using the cards
loaded into Wallet on either their iPhone running iOS 10 or their Apple Watch running watchOS 3 when
paired with a MacBook running macOS Sierra.

### iPhone payment flow

At the start of the checkout flow the user is prompted to select what time they would like their food
delivered for (or be ready for collection) and an optional note for the restaurant.

At first the user is shown the Apple Pay button in additional to the usual button to continue through
checkout to provide their delivery and payment details.

The user taps the Apple Pay button and the Apple sheet is displayed. Then the user selects their payment
card as well as their delivery address. While this happens we asynchronously validate the merchant session
to enable TouchID to authorize payment as well as validate that the restaurant selected delivers to the
postcode provided by the user in the case of a delivery order.

Once the user authorizes payment with their finger or thumb, the sheet is dismissed, they are logged in
to a guest account if not already logged in, and redirected to the order confirmation page.

{{< cdn-image path="apple-pay-step-1-ios.png" title="The Apple Pay button displayed during checkout in Safari on iOS 10." >}}

{{< cdn-image path="apple-pay-step-2-ios.png" title="The Apple Pay payment sheet in iOS." >}}

### macOS payment flow

At the start of the checkout flow the user is prompted to select what time they would like their food
delivered for (or be ready for collection) and an optional note for the restaurant.

Here the user is shown the Apple Pay button in additional to the usual button to continue through checkout
to provide their delivery and payment details.

{{< cdn-image path="apple-pay-step-1-macos.png" title="The Apple Pay button displayed during checkout in Safari on macOS Sierra." >}}

The user clicks the Apple Pay button and the Apple sheet is displayed. The user selects their payment
card as well as their delivery address. While this happens we asynchronously validate the merchant session
to enable the ability to authorize payment using an iPhone, iPad or Apple Watch paired with the signed in
iCloud account, as well as validate that the restaurant selected delivers to the postcode provided by the
user in the case of a delivery order.

{{< cdn-image path="apple-pay-step-2-macos.png" title="The Apple Pay payment sheet in macOS Sierra." >}}

Once the merchant session is validated, the user is then prompted to authorize the payment on their paired
device, for example using either an iPhone with TouchID or an Apple Watch.

{{< cdn-image path="apple-pay-step-3-macos.png" title="Payment confirmation for a purchase from macOS using Touch ID on an iPhone." >}}

{{< cdn-image path="apple-pay-step-3-watchos.png" title="Payment confirmation for a purchase from macOS using Apple Watch." >}}

Once the user authorizes payment with their finger or thumb with TouchID or by pressing a button on their
Apple Watch, the sheet is dismissed, they are logged in to a guest account if not already logged in, and
redirected to the order confirmation page.

Now the user just needs to wait for their food with their inner food mood to be prepared.

## Example integration

An example integration of Apple Pay JS adapted from our own implementation is available on [GitHub][sample-code].
You should be able to use it as a guide to implementing Apple Pay into your website by viewing the
[JavaScript][sample-code-js] for creating an ApplePaySession and the [C#][sample-code-cs] for validating a
merchant session. Also, provided you have an Apple Developer account so that you can generate your own merchant
identifier and the associated certificates, you should also be able to run it yourself and see Apple Pay in action.

## Conclusion

We hope you’ve found this post about how we brought Apple Pay to the Just Eat website informative and
interesting, and that the example integration is a useful resource if you’re thinking about implementing
Apple Pay into your own e-commerce solution yourself.

It’s been an interesting SDK to integrate with a number of challenges along the way, but we’ve also learned
a lot in the process, particularly about Apple Pay itself, as well as the differences between ASP.NET and
ASP.NET Core (the good and the not so good).

Just Eat is here to help you find your flavour, and with Apple Pay as a payment option in our website now, we
hope you’ll now be able to find it even easier!

[annoucement]: https://developer.apple.com/videos/play/wwdc2016/703/
[apple-pay]: https://www.apple.com/uk/apple-pay/
[apple-pay-applepaysession]: https://developer.apple.com/documentation/applepayontheweb/applepaysession/applepaysession
[apple-pay-begin]: https://developer.apple.com/documentation/applepayontheweb/applepaysession/begin
[apple-pay-css]: https://developer.apple.com/documentation/applepayontheweb/displaying-apple-pay-buttons-using-css
[apple-pay-completemerchantvalidation]: https://developer.apple.com/documentation/applepayontheweb/applepaysession/completemerchantvalidation
[apple-pay-js]: https://developer.apple.com/documentation/applepayontheweb
[apple-pay-js-polyfill]: https://github.com/justeat/applepayjs-polyfill
[apple-pay-merchant-session-server]: https://github.com/tomdale/apple-pay-merchant-session-server
[apple-pay-onpaymentauthorized]: https://developer.apple.com/documentation/applepayontheweb/applepaysession/onpaymentauthorized
[apple-pay-onvalidatemerchant]: https://developer.apple.com/documentation/applepayontheweb/applepaysession/onvalidatemerchant
[apple-pay-paymentrequest]: https://developer.apple.com/documentation/applepayontheweb/applepaypaymentrequest
[apple-pay-sandbox]: https://developer.apple.com/apple-pay/sandbox-testing/
[aspnet-core]: https://learn.microsoft.com/aspnet/core/introduction-to-aspnet-core
[aspnet-core-data-protection]: https://learn.microsoft.com/aspnet/core/security/data-protection/introduction
[aspnet-core-iis]: https://learn.microsoft.com/aspnet/core/host-and-deploy/iis
[aspnet-core-perf-counters]: https://github.com/dotnet/aspnetcore/issues/1319
[aspnet-perf-counters]: https://learn.microsoft.com/en-us/previous-versions/fxk122b4(v=vs.140)
[autofixture]: https://github.com/AutoFixture/AutoFixture/issues/404
[aws-ec2]: https://aws.amazon.com/ec2/
[azure-app-service]: https://azure.microsoft.com/products/app-service
[azure-portal]: https://azure.microsoft.com/get-started/azure-portal
[bower]: https://bower.io/
[chocolatey]: https://chocolatey.org/
[cors]: https://en.wikipedia.org/wiki/Cross-origin_resource_sharing
[googlebot]: https://developers.google.com/search/docs/crawling-indexing/overview-google-crawlers
[grafana]: https://grafana.com/
[gulp]: https://gulpjs.com/
[kibana]: https://www.elastic.co/kibana
[original-post]: https://web.archive.org/web/20161015205706/http://tech.just-eat.com:80/2016/10/10/bringing-apple-pay-to-the-web/
[just-eat-apple-pay]: https://web.archive.org/web/20161015205706/http://tech.just-eat.com/2015/07/14/the-journey-of-apple-pay-at-just-eat/
[just-eat-ios]: https://itunes.apple.com/gb/app/just-eat-takeaway-food-delivery/id566347057
[just-eat-website]: https://www.just-eat.co.uk/
[nlog-structured-logging]: https://github.com/justeat/NLog.StructuredLogging.Json
[nlog-structured-logging-issue]: https://github.com/justeat/NLog.StructuredLogging.Json/issues/3
[npm]: https://www.npmjs.com/
[pass-kit]: https://developer.apple.com/documentation/passkit
[payment-token]: https://developer.apple.com/documentation/passkit/payment-token-format-reference
[pfx]: https://en.wikipedia.org/wiki/PKCS_12
[polyfill]: https://en.wikipedia.org/wiki/Polyfill_(programming)
[sample-code]: https://github.com/justeattakeaway/ApplePayJSSample
[sample-code-cs]: https://github.com/justeattakeaway/ApplePayJSSample/blob/main/src/ApplePayJS/Controllers/HomeController.cs
[sample-code-js]: https://github.com/justeattakeaway/ApplePayJSSample/blob/main/src/ApplePayJS/wwwroot/js/site.js
[statsd]: https://github.com/statsd/statsd
[statsd-nuget]: https://github.com/justeattakeaway/JustEat.StatsD
