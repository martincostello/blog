---
title: How To Over-Engineer A Solution To A Domestic Problem
date: 2016-06-23
tags: azure,asp.net identity
layout: bloglayout
description: "Using ASP.NET Identity with Windows Azure Table Storage and Twitter to help feed your fish."
---

READMORE

<!--
Preamble about getting the fish and the "who fed them last" and the "who cleaned them last" problem.
Extending existing website to include /fish/.
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
-->
