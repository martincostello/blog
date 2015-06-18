---
title: Why I Switched From WordPress To Middleman
date: 2015-06-21
tags: blogging,middleman,wordpress
layout: bloglayout
---

A few years ago I thought I'd set up a blog. Initially I created a blog in [Blogspot](http://martincostello.blogspot.co.uk/) but I decided some time later that I'd rather host it myself with custom DNS, etc., mainly as a learning exercise. As I'm mostly a developer in the Microsoft stack, I decided I'd set it up in Windows Azure as an Azure Website (now a "Microsoft Azure Web App") as that was something I knew of and knew a little about. A few clicks through a wizard later and I had a WordPress blog running in Azure, backed by a MySQL database. Great - time to get blogging!

Flash-foward a few years, and I've got a sum total of [one solitary blog post](https://blog.martincostello.com/ensuring-your-asp-net-website-is-secure/). Yeah, so I've been a bit slack on the whole writing a blog thing. However I've got an idea for a second blog post that I've been procrastinating over writing for a while, so I thought I'd start on that. By this point I'd grown two different Azure subscriptions and the blog was running in the wrong one and I was starting to hit limits on my free Azure credits due to other usage, so I figured I'd switch it around. The problems begin.

READMORE

## Problems - Part 1

So the first problem was that the MySQL database that Azure automatically provisioned for me when the WordPress blog was initially provisioned is through a third-party provider. I had no idea where this was, but eventually through some clicking of buried-away links in the Azure portal I found a link through to the third-party portal for the database. It was then that I discovered that the database was on a free tier with a paltry 20MB maximum size. 20MB seemed a bit pathetic considering with one post I'd already used 15MB, and I already wanted to move the database to a different subscription so why remove the limit and start paying more? So how do you move a MySQL database from one subscription to another? I don't know, I never found out - more on this later.

The next hurdle was that I'm not really au-fait with MySQL management given I'm a Microsoft/Windows guy, and if I'm honest I wasn't particularly inclined to learn to do so either.  Microsoft have been pushing for SQL Server interoperability with PHP (which WordPress is written in) for [a while](http://blogs.technet.com/b/dataplatforminsider/archive/2014/11/06/available-today-preview-release-of-the-sql-server-php-driver.aspx) so I thought why not migrate to use SQL Server as the data store instead? That way I can host a SQL database (which I know how to do) in Azure in the right subscription and point WordPress at that instead. There's [PHP drivers for SQL Server]
(https://msdn.microsoft.com/en-us/data/ff657782.aspx) now and a [WordPress plugin](https://wordpress.org/plugins/wordpress-database-abstraction/) for using SQL Server as well? Microsoft have even [blogged about](http://blogs.msdn.com/b/brian_swan/archive/2010/05/12/running-wordpress-on-sql-server.aspx) how to do it, so it can't be that hard, right? Wrong.

## The Abortive Attempt To Use SQL Server

Migrating to SQL Server it is then. Let's get started. Step one: where the hell is the website source?

As everything had been set up for me in the Azure wizard, I'd never actually seen the website source itself, well, ever. Better get it then, which is pretty simple. I just had to FTP into the IIS website's directory in Azure and pull it out.

Great I've got the source, now what do I do? Well I better put it in source control somewhere before I start tinkering. That way I can rollback if I mess everything up, plus I can setup a test slot somewhere where I can do testing without worrying about screwing up my "production" MySQL database. Azure has a nice [deploy from Git workflow](https://azure.microsoft.com/en-gb/documentation/articles/web-sites-publish-source-control/) so I'll go with a Git repo to look after it. There's some private configuration data for the site to run in Azure though (like connection strings) so I don't want it publicly available. GitHub don't allow private repositories for free but Visual Studio Online is always private, supports Git and is [free for up to 5 users](https://www.visualstudio.com/en-us/products/visual-studio-online-pricing-vs.aspx). I'm only one person, so great, sounds like the perfect fit. I'll get all the code checked in and branch and get to work then.

