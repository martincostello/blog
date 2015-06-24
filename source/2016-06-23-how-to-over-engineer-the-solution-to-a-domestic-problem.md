---
title: How To Over-Engineer A Solution To A Domestic Problem
date: 2016-06-23
tags: azure,asp.net identity
layout: bloglayout
description: "Using ASP.NET Identity with Windows Azure Table Storage and Twitter to help feed your fish."
---

My flatmates and I recently got some fish which live in a tank in the hall of our flat. Fish being fish, they need feeding once a day and can't be fed more than that as they have no sense of being full and will just eat until they explode. With there being three of us with our own comings and goings day-to-day, being able to keep track of who last fed the fish and when becomes a bit of a hassle without having to have a whiteboard or message each other every day etc.

Me being me, I decided I'd find a way to put the skills of my day job into practice and find a technological solution to the problem. After all, why use a whiteboard when you can use a website right? Right?

Microsoft have been pushing [ASP.NET Identity](http://www.asp.net/identity/overview/getting-started/introduction-to-aspnet-identity) as the new go-to solution for authentication and authorization for a while now. It's now the default in the templates for ASP.NET applications for Visual Studio instead of the old default of [ASP.NET Membership](https://msdn.microsoft.com/en-us/library/yh26yfzy(v=vs.140).aspx).

Given that I thought I'd use this as a learning opportunity for ASP.NET Identity by using it to handle the authentication part of the problem so I can track *who* has fed the fish. Time to roll up my metaphorical sleeves and get started...

READMORE

## The Starting Point

I already host my own [website](https://martincostello.com/) so I've got a starting point for the solution. It's ASP.NET MVC, so plugging in ASP.NET Identity should be relatively easy. It's also hosted as an [Azure Web App]
(http://azure.microsoft.com/en-gb/services/app-service/web/) so I don't need to worry too much about hosting and provisioning resources I need or paying the earth to use them.

Based on that, all I need to do is plug-in the ASP.NET Identity OWIN middleware from [NuGet](https://www.nuget.org/packages/Microsoft.AspNet.Identity.Owin/) and I'm ready to get set implementing the fish feeding tracker problem domain.

## Customising ASP.NET Identity

It turns out that for my use case it's not as simple as that. ASP.NET Identity only ships with one implementation out-of-the-box, and that's for using [EntityFramework](https://msdn.microsoft.com/en-us/data/ef.aspx?f=255&MSPPError=-2147217396) as the backing data store.

As the cost of running all this infrastructure is coming out of my own pocket, I don't want to run an entire SQL database in Azure just to hold a bit of metadata about users and their roles to drive the website authentication mechanisms.  Now I could use a local MDF file and attach it and store it in the ```App_Data``` folder, but my website runs on two Azure website instances for load-balancing and that solution isn't scalable for this scenario and means that it isn't backed up or easily migrated at a later date.

[Azure Table Storage](https://azure.microsoft.com/en-gb/documentation/articles/storage-dotnet-how-to-use-tables/) seems like a good fit for what I want to do, but I don't want to write a whole implementation of ASP.NET Identity that uses Azure Table Storage all by myself. Luckily I didn't have to as [one has already been written](https://identityazuretable.codeplex.com/)!
 
<!--
How to go about plugging in El Camino, including customising it to use a connection string.
Not wanting to run my own authentication and authorization service - so using third party OAuth instead.
Securing the page with roles.
Creating apps for Microsoft and Google Authentication.
Setting up IIS/Visual Studio for local development with Google/MS Auth.
Creating Twitter app for Twitter Authentication and how it doesn't allow you to get email addresses.
Creating Twitter app for the dish to tweet as gamification to incentivise feeding.
Integrating ASP.NET Identity.
Plugging in El-Camino NuGet package (https://identityazuretable.codeplex.com/) to use Azure Table Storage instead of EntityFramework.
Integrating the Twitter API for tweeting, including difficulty of integrating OAuth, including reference to excellent docs on Twitter dev site and creating a unit test from the docs.
Using scopes, claims and roles to secure write access and get data to allow secure registration.
Storing the metadata about when the fish were fed and cleaned and by who in Azure Blob Storage as JSON.
Pitfall of assuming users fill in their profiles (e.g. Andrew with no first name meaning I displayed his email).
(When done) how to setup a notification system for if they aren't fed.
Lessons learned.
Summary/conclusion.
Code examples where appropriate.
-->