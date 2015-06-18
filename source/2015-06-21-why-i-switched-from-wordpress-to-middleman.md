---
title: Why I Switched From WordPress To Middleman
date: 2015-06-21
tags: blogging,middleman,wordpress
layout: bloglayout
---

A few years ago I thought I'd set up a blog. Initially I created a blog in [Blogspot](http://martincostello.blogspot.co.uk/) but I decided some time later that I'd rather host it myself with custom DNS, etc., mainly as a learning exercise. As I'm mostly a developer in the Microsoft stack, I decided I'd set it up in Windows Azure as an Azure Website (now a "Microsoft Azure Web App") as that was something I knew of and knew a little about. A few clicks through a wizard later and I had a WordPress blog running in Azure, backed by a MySQL database. Great - time to get blogging!

Flash-foward a few years, and I've got a sum total of [one solitary blog post](https://blog.martincostello.com/ensuring-your-asp-net-website-is-secure/). Yeah, so I've been a bit slack on the whole writing a blog thing. However I've got an idea for a second blog post that I've been procrastinating over writing for a while, so I thought I'd start on that. By this point I'd grown two different Azure subscriptions and the blog was running in the wrong one and I was starting to hit limits on my free Azure credits due to other usage, so I figured I'd switch it around. The problems begin.

READMORE

## Problems, Problems, Problems

So the first problem was that the MySQL database that Azure automatically provisioned for me when the WordPress blog was initially provisioned is through a third-party provider. I had no idea where this was, but eventually through some clicking of buried-away links in the Azure portal I found a link through to the third-party portal for the database. It was then that I discovered that the database was on a free tier with a paltry 20MB maximum size. 20MB seemed a bit pathetic considering with one post I'd already used 15MB, and I already wanted to move the database to a different subscription so why remove the limit and start paying more? So how do you move a MySQL database from one subscription to another? I don't know, I never found out - more on this later.

The next hurdle was that I'm not really au-fait with MySQL management given I'm a Microsoft/Windows guy, and if I'm honest I wasn't particularly inclined to learn to do so either.  Microsoft have been pushing for SQL Server interoperability with PHP (which WordPress is written in) for [a while](http://blogs.technet.com/b/dataplatforminsider/archive/2014/11/06/available-today-preview-release-of-the-sql-server-php-driver.aspx) so I thought why not migrate to use SQL Server as the data store instead? That way I can host a SQL database (which I know how to do) in Azure in the right subscription and point WordPress at that instead. There's [PHP drivers for SQL Server]
(https://msdn.microsoft.com/en-us/data/ff657782.aspx) now and a [WordPress plugin](https://wordpress.org/plugins/wordpress-database-abstraction/) for using SQL Server as well? Microsoft have even [blogged about](http://blogs.msdn.com/b/brian_swan/archive/2010/05/12/running-wordpress-on-sql-server.aspx) how to do it, so it can't be that hard, right? Wrong.

## The Abortive Attempt To Use SQL Server

Migrating to SQL Server it is then. Let's get started. Step one: where the hell is the website source?

As everything had been set up for me in the Azure wizard, I'd never actually seen the website source itself, well, ever. Better get it then, which is pretty simple. I just had to FTP into the IIS website's directory in Azure and pull it out.

Great I've got the source, now what do I do? Well I better put it in source control somewhere before I start tinkering. That way I can rollback if I mess everything up, plus I can setup a test slot somewhere where I can do testing without worrying about screwing up my "production" MySQL database. Azure has a nice [deploy from Git workflow](https://azure.microsoft.com/en-gb/documentation/articles/web-sites-publish-source-control/) so I'll go with a Git repo to look after it. There's some private configuration data for the site to run in Azure though (like connection strings) so I don't want it publicly available. GitHub don't allow private repositories for free but Visual Studio Online is always private, supports Git and is [free for up to 5 users](https://www.visualstudio.com/en-us/products/visual-studio-online-pricing-vs.aspx). I'm only one person, so great, sounds like the perfect fit. I'll get all the code checked in and branch and get to work then.

Cue montage of trying to get it to work to the theme of Murder She Wrote. I'll let this selection of tweets show you some highlights of the journey:

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">PHP confuses me.</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/564470541428740096">February 8, 2015</a></blockquote>
<script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">On reflection, staying up until 3am messing about with PHP wasn&#39;t the best idea.</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/564623522711236608">February 9, 2015</a></blockquote>

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">Now, can I solve this PHP mystery in 25 minutes before I have to go out? *Cue Mission:Impossible theme*</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/564758186029506561">February 9, 2015</a></blockquote>

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">Computers: the source of my income and maybe also a killing spree in the imminent future.</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/565600543645917184">February 11, 2015</a></blockquote>

## Mission: Failed

As you can see, I gave up. The main problems and reasons being:

  1. The WordPress plug-in was old and out-of-date (2 years+ since the last update) and didn't work properly with my version of WordPress;
  1. The SQL Server PHP drivers didn't seem to want to run on PHP 5.5. After much banging my head against the wall I discovered that not only did they work fine with PHP 5.4, but also that the PHP drivers were already installed in Azure Websites meaning that lots of playing around trying to find the right binaries and get them to load were pointless;
  1. I could not work out how to import the MySQL data in SQL Server successfully without violating unique constrains and/or losing data integrity.

## A Change Of Tack

After the WordPress/SQL Server debacle I went into hibernation on the whole blog situation for a few months until this week. I'd seen various chatter here and there over the last few months about static site generators, so I wondered whether that would be a viable option. So I issued this tweet:

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">NERDS: recommend me a static site generator. I want to replace a WordPress blog that has fuck-all in it.</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/610525869497565184">June 15, 2015</a></blockquote>

I didn't get my useful feedback from my followers, so I went to our friend Google.  It led me to the following two links:

   1. [Static Site Generators](https://staticsitegenerators.net/) - Just a big list, not very helpful;
   1. [StaticGen](https://www.staticgen.com/) - A much better curated list, ranked by the projects' GitHub star counts.

The main one that lept out was [Jekyll](http://jekyllrb.com/) as I'd heard of it before and GitHub uses it to generate GitHub pages sites. However not long afterwards I discovered that [Jekyll doesn't officially support Windows](http://jekyllrb.com/docs/windows/). I'm mainly a Windows guy, and even though I have an Ubuntu VM in Hyper-V on my laptop, I'd rather stick with the environment and tools I know, so I ruled it out of the running.

Cue some head-scratching, after which I did some further googling and found [this post by Scott Hanselman](http://www.hanselman.com/blog/RunningTheRubyMiddlemanStaticSiteGeneratorOnMicrosoftAzure.aspx). [Middleman](https://middlemanapp.com/) was in the second row on [StaticGen](https://www.staticgen.com/). Interesting...

Having read Scott's article, I summised that Middleman worked on Windows, and would work in Azure (it's a static site I know, but good to know the workflow has been tried and tested by someone else). The article is mostly about getting the site to *build* on Azure, which **for now** I'm not interested in. That's a nice to have I can do in the future (it's on my VS Online backlog in fact), but at the time I just wanted the site - I can deploy it via FTP in the short-term.

## Getting To Grips With Middleman

Reading the [Middleman](https://middlemanapp.com/basics/install/) documentation things seemed straight-forward:

  1. HTML templating from HTML and Markdown to HTML;
  1. Builds with Ruby;
  1. Generates a static site ready for deployment.

As ever, I steamed right ahead and got stuck in, having not *thoroughly* read the documentation (like most developers). I had a site template, and it built. Great.

Now how do you write a blog with it?

It turns out (having read the documentation again *properly*) that Middleman has a [blog plug-in](https://middlemanapp.com/basics/blogging/). Huzzah!

More digging around in the generated code shows the following blog features:

  1. Archiving;
  1. Tags;
  1. Atom Feed;
  1. XML site map.

This all looks very encouraging. In fact, this level of encouraging:

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">Found using the Middleman static site generator surprisingly easy to get to grips with. Wordpress: your days are numbered...</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/610927266311356417">June 16, 2015</a></blockquote>

## Implementing The Blog


Then it was just a simple matter of pushing the final build of the site to Azure over FTP, reconfiguring my DNS and deleting the old Azure resources using the subscription I didn't want to use any more. Boom - Middleman static site in production.

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">So long Wordpress.&#10;Bye bye MySQL.&#10;Hello Middleman.</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/611300636324462592">June 17, 2015</a></blockquote>

## And The End Result?

I think this tweet, and the fact that you are reading this blog post, speak for themselves:

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">Two evenings of effort and my blog re-architecture is done and Wordpress is in the bin:  <a href="https://t.co/2gD4SGzE4p">https://t.co/2gD4SGzE4p</a></p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/611430850060812290">June 18, 2015</a></blockquote>
<script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>

Happy Martin :)
