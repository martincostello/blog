---
title: Ensuring Your ASP.NET Website Is Secure
date: 2014-03-04
tags: security
showTitle: true
---

So recently I've been doing some work ensuring some websites I work on are secure. This has been from a mix of hands-on testing of them myself, dealing with feedback from dedicated security testers testing the applications, and this week attending an "ASP.NET Secure Coding" training course.

From my own testing I found the odd thing here and there during development based on what I've read in the past is best-practice. These were exclusively application/coding changes. Fixing these was a mix of "Oh yeah" realisations or a quick Google leading to MSDN or Stack Overflow, leading to some simple one-line changes here and there.

The same was true of the results from the dedicated security testers. This was slightly more work, as they were very good at saying "X is an issue", but almost useless at saying how to fix it. They also did some tests which were more of the server and network configuration, which in some cases fell out of my personal work remit. However, a secure app is a secure app, meaning that you just can't ignore it because it's not controllable by the code. This meant yet further reading to find out how to fix the software issues, as well as reading further afield to find out how to fix the server and network configuration issues as well.

Then there was the security testing course. The title was a bit of a misnomer, as it wasn't so much "how to code securely" as "how to find security problems". It was very useful as it was quite eye-opening to discover what seemingly innocent "oh that's not important" small niggly things could, in the hands of a skilled "hacker", actually lead to your machine being completely owned by an attacker.

However, after three rounds of realisation, fixing and testing, there was one common theme I found with all of this - there was no central resource detailing how to fix all of the issues that came up. There were a lot of resources where just one problem would be described (and sometimes a fix for it described), but a lot of the time there'd be a page about a problem, but you'd need to go to a completely different one for the fix. Some required some creative Googling to find, others were right there (if you knew what you were looking for).

So, Dear Reader, why have I written this? Well, I thought it would be a good idea to collate all the stuff that's best practice into a single blog post, and then include for each one the instructions of how to fix it. Helpful right? Well, at least I hope so.

READMORE

I've arranged them by flaw/requirement, with a quick explanation for each and then the steps to fix. Some fixes are code, some are Web.config settings, and others require digging around in the Registry. After that there's some links to resources you can use to help test your site to check for a number of vulnerabilities you want to be protected against.

In the spirit of [Scott Hanselman](http://www.hanselman.com/) I'd just like to point out: **any changes you make are at your own risk**. While these changes worked for me, make sure you test them yourself to ensure they solve the issue for you. Also, these fixes may not solve a particular vulnerability fully. I believe they do, but I don't have the full resources to exhaustively test every single one. Also, some may not be appropriate for your website. If that's true, that's your call - after all, it's your website. Basically *caveat emptor*.

With no further ado, the things to make sure you do...

## Require SSL

This is an IIS setting, and it's pretty easy to enable. This ensures your IIS server returns a HTTP ```301 Moved Permanently``` or ```302 Found``` HTTP code and redirects to the HTTPS version of your site. This is to protect against main-in-the-middle and Strip HTTPS attacks.

For example if a user browses to [http://martincostello.com/](http://martincostello.com/) they will receive an HTTP ```301 Moved Permanently``` and be redirected to [https://martincostello.com/](https://martincostello.com/) instead.

*Updated 08/02/2015*

If you don't have access to the full IIS configuration (for example you are using Azure Websites), and are using ASP.NET MVC, you can use the [RequireHttpsAttribute](https://msdn.microsoft.com/en-us/library/system.web.mvc.requirehttpsattribute%28v=vs.118%29.aspx) attribute in your filters as shown below. This only works for requests processed by the MVC pipeline, so won't work for static content, for example.

```
using System.Web.Mvc;
 
namespace MyWebsite
{
    internal static class FilterConfig
    {
        internal static void RegisterGlobalFilters(GlobalFilterCollection filters)
        {
            filters.Add(new RequireHttpsAttribute());
        }
    }
}
```

If you can't easily run any custom code like the MVC example above, you can use an IIS URL Rewrite rule in Web.config to do this instead, as shown below:

```
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <rule name="Redirect to HTTPS" stopProcessing="true">
          <match url="(.*)" />
          <conditions>
            <add input="{HTTPS}" pattern="off" ignoreCase="true" />
          </conditions>
          <action type="Redirect" url="https://{HTTP_HOST}/{R:1}" redirectType="Permanent" />
        </rule>
      </rules>
    </rewrite>
  </system.webServer>
</configuration>
```

*Updated 17/06/2015*

If you are using MVC, as of [ASP.NET 5.2.4](http://aspnetwebstack.codeplex.com/SourceControl/changeset/9efcaf3315962488823acead507db18bf5c3e29d) you can issue an HTTP ```301``` instead of a HTTP ```302``` in the following way:

```
using System.Web.Mvc;
 
namespace MyWebsite
{
    internal static class FilterConfig
    {
        internal static void RegisterGlobalFilters(GlobalFilterCollection filters)
        {
            filters.Add(new RequireHttpsAttribute(permanent: true));
        }
    }
}
```

As of ASP.NET MVC 6 only permanent redirects are supported.

## Use Anti-Forgery Tokens

If using MVC you should use anti-forgery tokens when submitting your forms via HTTP POST. This protects against Cross-Site Request Forgery (CSRF) [attacks](https://en.wikipedia.org/wiki/Cross-site_request_forgery). This is a two-step process.

First, generate an anti-forgery token in your forms in your views:

```
@Html.AntiForgeryToken()
```

Second, apply the attribute to your controller action methods that are posts:

```
[HttpPost]
[ValidateAntiForgeryToken]
public ActionResult LogOff()
{
    WebSecurity.Logout();
    return RedirectToAction("Index", "Home");
}
```

Something to watch out for here is if you do AJAX POST requests from your views. These also need to be CSRF safe, but can't use the same mechanism as for your action links, forms etc. A good description of how to fix this can be found on the [ASP.NET website](http://www.asp.net/web-api/overview/security/preventing-cross-site-request-forgery-%28csrf%29-attacks).

## Enable IIS Custom Errors

This is a pretty simple one. As well as improving the user-experience, it makes sure you don't accidentally leak error details (e.g. stack traces) to clients in the event of an exception occurring. There's two settings for this, one for IIS 6 and one for IIS 7. It's best to set both of these, so the below should be present as a bare minimum. Obviously, you can customise these further - see MSDN for details.

```
<configuration>
  <system.web>
    <customErrors mode="On" defaultRedirect="~/Error" />
  </system.web>
  <system.webServer>
    <httpErrors errorMode="Custom" />
  </system.webServer>
</configuration>
```

## Disable Debug Compilation

This isn't really a security setting, more of a performance one, but it can cause a number of features to be automatically disabled, so you shouldn't be running it in Debug in production. Simple to turn off:

```
<configuration>
  <system.web>
    <compilation debug="false" />
  </system.web>
</configuration>
```

## Disable SSL 2

SSL V2 is considered to be [cryptographically broken](https://en.wikipedia.org/wiki/Transport_Layer_Security#SSL_2.0), however it's enabled by default on many Windows servers. To disable it, add/edit the Registry key detailed in this Microsoft [KB article](https://support.microsoft.com/en-us/kb/187498).

## Disable RC4 Ciphers

RC4 ciphers are also considered to be [cryptographically broken](https://en.wikipedia.org/wiki/RC4), and again are enabled by default on many Windows servers. To disable it, add/edit the Registry key detailed in this Microsoft [KB article](https://support.microsoft.com/en-us/kb/245030).

## HTML Encode User-Supplied Input

You should never trust user input, **ever**, and never "mirror" it back to the user without HTML encoding it first. In MVC using the Razor view engine, this is handled for you automatically - you have to opt-in to shooting yourself in the foot.

For the first MVC view engine and for ASP.NET Forms, you need to ensure your HTML escape it yourself using one of the two syntaxes:

### ASP.NET Forms

```
HttpUtility.HtmlEncode(Model.Value)
```

### MVC

```
HtmlHelper.Encode(Model.Value)
```

## Don't Expose the IIS Version

The IIS version is exposed via the ```Server``` HTTP response header. It's best to disable this as it helps hide the version of server software you're using to make it just a bit harder for an attacker to find known vulnerabilities to use against you. If you're using IIS 7 and Integrated Pipeline mode, you can disable it this way:

```
protected void Application_PreSendRequestHeaders()
{
    this.Response.Headers.Remove("Server");
}
```

To make sure this is suppressed for all requests (e.g. for static content such as JavaScript), you should also add the following setting if you're using IIS 7:

```
<configuration>
  <system.webServer>
    <modules runAllManagedModulesForAllRequests="true" />
  </system.webServer>
</configuration>
```

*Updated 08/02/2015*

If you're using Azure Websites, you can also use the following setting in Web.config as documented here to remove the Server HTTP response header:

```
<configuration>
  <system.webServer>
    <security>
      <requestFiltering removeServerHeader="true" />
    </security>
  </system.webServer>
</configuration>
```

## Don't Expose the ASP.NET Version

As with the IIS version, for the same reasons it's also best to hide the ASP.NET version you're using to clients as well. You can accomplish this with the following Web.config setting in IIS 7.

```
<configuration>
  <system.webServer>
    <httpProtocol>
      <customHeaders>
        <remove name="X-Powered-By" />
      </customHeaders>
    </httpProtocol>
  </system.webServer>
</configuration>
```

*Updated 08/02/2015*

If you don't have access to the full IIS configuration (for example you are using Azure Websites) and can't run any custom code to remove the ```X-Powered-By``` header, than you can use an IIS URL Rewrite rule to blank the value instead.

```
<configuration>
  <system.webServer>
    <rewrite>
      <outboundRules rewriteBeforeCache="true">
        <rule name="Remove X-Powered-By HTTP response header">
          <match serverVariable="RESPONSE_X-Powered-By" pattern=".+" />
          <action type="Rewrite" value="" />
        </rule>
      </outboundRules>
    </rewrite>
  </system.webServer>
</configuration>
```

## Don't Expose the ASP.NET MVC Version

As above (again), you should also hide the MVC version you're using. This is a simple one-line code change:

```
MvcHandler.DisableMvcResponseHeader = true;
```

## Secure The Root Of Your Site

If you use a virtual directory for your site, you might have the root of the website just pointing to ```C:\Inetpub\wwwroot``` and it's default settings. This will undo all of the changes you make in your sub-directory for security if someone just navigates to the root.

The best way to deal with this is to manually configure the root site to:

  1. Require HTTPS;
  1. Redirect all requests to the root to your virtual directory.

If you do this, two things to consider:

  1. Ensure that any IIS 7 custom error pages include the full path to your site in the virtual directory.
  1. You disable HTTP redirects on the site in your virtual directory. In IIS 7, you do this with the following Web.config setting.

```
<configuration>
  <system.webServer>
    <httpRedirect enabled="false" />
  </system.webServer>
</configuration>
```

## Use Strict Transport Security

This is again designed to help prevent man-in-the-middle and HTTPS Strip attacks. More information can be found at[here](https://en.wikipedia.org/wiki/HTTP_Strict_Transport_Security).

To enable this, add the following to your Global.asax file:

```
protected void Application_PreSendRequestHeaders()
{
    if (this.Request.IsSecureConnection)
    {
        this.Response.AppendHeader("Strict-Transport-Security", "max-age=31536000");
    }
}
```

In the above example, this instructs the browser to use strict transport security for a year, though this is specified for each request so essentially regenerates itself.

## Use HTTP-Only and SSL-Only Cookies

To protect your sites users' cookies from being accessed by scripts in other domains and protect against them being read from min-in-the-middle attackers, ensure the following settings is enabled in Web.config:

```
<configuration>
  <system.web>
    <httpCookies httpOnlyCookies="true" requireSSL="true" />
  </system.web>
</configuration>
```

## Require SSL For Forms Authentication

Similar to the above, ensure that you use SSL for the cookies used for Forms authentication, assuming you're using the (more secure) cookie-based version, rather than the query string version to store the forms authentication cookie.

```
<configuration>
  <system.web>
    <authentication mode="Forms">
      <forms requireSSL="true" />
    </authentication>
  </system.web>
</configuration>
```

## Prevent Click-Jacking and Framing

[Click-Jacking](https://en.wikipedia.org/wiki/Clickjacking) and framing is where an attacker uses an IFrame to either host their own scripts around the site you want to visit so they can inspect what you're doing, and/or force/persuade you to click a link to execute an action they want you to perform because they can't do it automatically because of browser security features.
You can prevent framing in IIS 7 Integrated Pipeline mode with this code snippet:

```
protected void Application_PreSendRequestHeaders()
{
    this.Response.AppendHeader("X-Frame-Options", "DENY");
}
```

If other sites you manage in the same domain need to frame your site for some reason, then you can use this instead:

```
protected void Application_PreSendRequestHeaders()
{
    this.Response.AppendHeader("X-Frame-Options", "SAMEORIGIN");
}
```

**N.B.** Preventing framing will prevent you from using tools such as mobile browser emulators. If you need to use such tools, consider making the value configurable. If you choose to do this make sure that the default value is **DENY** and that you don't have it enabled on your production site, only use your configurable "off" switch on environments such as dev/QA/staging/UAT.

*Updated 08/02/2015*

If you don't need any runtime configurability and are using at least IIS 7.0, you could also achieve this via Web.config settings are shown below.

```
<configuration>
  <system.webServer>
    <httpProtocol>
      <customHeaders>
        <add name="X-Frame-Options" value="DENY" />
      </customHeaders>
    </httpProtocol>
  </system.webServer>
</configuration>
```

To help further framing, you can use the following Javascript in your page layouts to force your site to "burst" to the top of the frames in the browser it's being rendered in. This helps eliminate the site from being contained in iframes:

```
<script type="text/javascript">
    if (self == top) {
        document.documentElement.className = document.documentElement.className.replace(/\bjs-flash\b/, '');
    }
    else {
        top.location = self.location;
    }
</script>
```

## Don't Cache Secure Content

If you have secure content on your site (i.e. content you need to be logged in to see), then you should not cache this content. It provides a way for an attacker to potentially access this content via the browser cache, even if the user is not logged in in the current session. The easiest way to do this is to update your Master page or _Layout.cshtml file to add the following HTTP meta tags:

```
<meta http-equiv="Cache-Control" content="no-cache, no-store" />
<meta http-equiv="Pragma" content="no-cache" />
```

## Set Your Machine Encryption and Decryption Keys

Everyone likes to be successful, and if your site is you might need to scale out and add new machines to your server farm to handle the load. Even if you don't think you'll get that far, it's a good idea to set the machine keys used to secure things like for forms authentication ticket in your Web.config file explicitly up-front so that you're ready to handle scale out. You can use [this tool on my website](https://www.martincostello.com/tools/#GenerateMachineKey) to generate your keys, then you just need to add them to this setting:

```
<configuration>
  <system.web>
    <machineKey decryption="AES" decryptionKey="{Your Decryption Key}" validation="SHA1" validationKey="{Your Validation Key}"/>
  </system.web>
</configuration>
```

## Disable Trace.axd

The Trace.axd HTTP handler is great for debugging, but can leak lots of nasty details to browsers to it if you leave it enabled on your production servers. For example, you could leak users' user names and passwords from your log on page. Eeek! This is a doddle to turn off, and is off by default anyway. But it's always good to be explicit and put it in your file and get any installers and/or deployment scripts explicitly turn it off when you deploy to your production servers.

```
<configuration>
  <system.web>
    <trace enabled="false" localOnly="true" />
  </system.web>
</configuration>
```

## Testing Resources

I recommend the following resources to help test your sites security after you apply the necessary changes:

### asafaweb.com

[This site](https://asafaweb.com/) tests your ASP.NET site, and looks for a number of things I've mentioned above, as well as some other stuff I haven't. It found a few things I hadn't considered on my sites, and helped improve the overall quality.

### Qualys SSL Labs Server Tester

[This site](https://www.ssllabs.com/ssltest/index.html) tests the server configuration to test for use of things like SSL 2, RC4 ciphers and invalid SSL certificates. It's well worth using - I used it to validate that the changes for SSL v2 and RC4 being disabled as I described above had been accomplished correctly on our servers.

[This post is a re-post of the article that was originally published [here](http://martincostello.blogspot.co.uk/2013/09/ensuring-your-aspnet-website-is-secure.html).]

## Updated 08/02/2015

Over the last year or so, there's a number of other settings I've come across in .NET and in IIS that add to the list of things to check in your website. In fact, there's a few I found today when doing some maintenance on this blog today and hardening the configuration of this site while I was renewing my SSL certificates. Some are new things, and some are additional ways of doing things already listed. For the things already listed, I've added the new information to the entries above.

## Fixing "Insecure" Cookies In Azure Websites

If you're using Azure Websites, you'll find that HTTP requests to your website include a cookie that isn't HTTP-Only and isn't secure called [ARRAffinity](http://azure.microsoft.com/blog/2013/11/18/disabling-arrs-instance-affinity-in-windows-azure-web-sites/). If you don't need Sticky Sessions and you need to remove this cookie, you can apply the following Web.config change to remove it.

```
<configuration>
  <system.webServer>
    <httpProtocol>
      <customHeaders>
        <add name="Arr-Disable-Session-Affinity" value="True" />
      </customHeaders>
    </httpProtocol>
  </system.webServer>
</configuration>
```

## Requiring Secure Cookies For Role Manager

If you're using the ASP.NET Role Manager and are caching the user's role in a cookie, ensure that the cookie is set to require SSL in Web.config, as shown below.

```
<configuration>
  <system.web>
    <roleManager cookieRequireSSL="true" />
  </system.web>
</configuration>
```

## Use Secure Cookies For OWIN Cookie Authentication

If using OWIN Middleware for authentication (e.g. for Microsoft Accounts, Google Accounts, WS-Federation etc.), then sure you set the appropriate options when configuring cookie-based authentiation, as shown below.

```
var options = new CookieAuthenticationOptions()
{
    CookieHttpOnly = true,
    CookieSecure = CookieSecureOption.Always,
    ExpireTimeSpan = TimeSpan.FromMinutes(10),  // Set whatever appropriate lifetime your site requires for your security needs
};
```
