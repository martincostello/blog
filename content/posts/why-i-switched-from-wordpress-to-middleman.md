---
title: Why I Switched From WordPress To Middleman
date: 2015-06-18
tags: blogging,middleman,wordpress
layout: post
description: "Why I switched from using WordPress to the Middleman static site generator for writing my blog."
---

A few years ago I thought I'd set up a blog. Initially I created a blog in [Blogspot](http://martincostello.blogspot.co.uk/) but I decided some time later that I'd rather host it myself with custom DNS, etc., mainly as a learning exercise. As I'm mostly a developer in the Microsoft stack, I decided I'd set it up in Windows Azure as an Azure Website (now a "Microsoft Azure Web App") as that was something I knew of and knew a little about. A few clicks through a wizard later and I had a WordPress blog running in Azure, backed by a MySQL database. Great - time to get blogging!

Flash-foward a few years, and I had a sum total of [one solitary blog post](https://blog.martincostello.com/ensuring-your-asp-net-website-is-secure/). Yeah, so I'd been a bit slack on the whole writing a blog thing. However I've got an idea for a second blog post that I've been procrastinating over writing for a while, so I thought I'd start on that (aside: this isn't that blog post, that's coming soon). By this point I'd grown two different Azure subscriptions and the blog was running in the wrong one and I was starting to hit limits on my free Azure credits due to other usage, so I figured I'd switch it around. The problems begin.

<!--more-->

## Problems, Problems, Problems

So the first problem was that the MySQL database that Azure automatically provisioned for me when the WordPress blog was initially provisioned is through a third-party provider. I had no idea where this was, but eventually through some clicking of buried-away links in the Azure portal I found a link through to the third-party portal for the database. It was then that I discovered that the database was on a free tier with a paltry 20MB maximum size. 20MB seemed a bit pathetic considering with one post I'd already used 15MB, and I already wanted to move the database to a different subscription so why remove the limit and start paying more? So how do you move a MySQL database from one subscription to another? I don't know, I never found out - more on this later.

The next hurdle was that I'm not really au-fait with MySQL management given I'm a Microsoft/Windows guy, and if I'm honest I wasn't particularly inclined to learn to do so either.  Microsoft have been pushing for SQL Server interoperability with PHP (which WordPress is written in) for [a while](http://blogs.technet.com/b/dataplatforminsider/archive/2014/11/06/available-today-preview-release-of-the-sql-server-php-driver.aspx) so I thought why not migrate to use SQL Server as the data store instead? That way I can host a SQL database (which I know how to do) in Azure in the right subscription and point WordPress at that instead. There's [PHP drivers for SQL Server](https://msdn.microsoft.com/en-us/data/ff657782.aspx) now and a [WordPress plugin](https://wordpress.org/plugins/wordpress-database-abstraction/) for using SQL Server as well? Microsoft have even [blogged about](http://blogs.msdn.com/b/brian_swan/archive/2010/05/12/running-wordpress-on-sql-server.aspx) how to do it, so it can't be that hard, right? **Wrong** as I was about to discover.

## The Abortive Attempt To Use SQL Server

Migrating to SQL Server it is then. Let's get started. Step one: where the hell is the website source?

As everything had been set up for me in the Azure wizard, I'd never actually seen the website source itself ever. Better get it then, which is pretty simple. I just had to FTP into the IIS website's directory in Azure and pull it out.

Great I've got the source, now what do I do? Well I better put it in source control somewhere before I start tinkering. That way I can rollback if I mess everything up, plus I can setup a test slot somewhere where I can do testing without worrying about screwing up my "production" MySQL database. Azure has a nice [deploy from Git workflow](https://azure.microsoft.com/en-gb/documentation/articles/web-sites-publish-source-control/) so I'll go with a Git repo to look after it. There's some private configuration data for the site to run in Azure though (like connection strings) so I don't want it publicly available. GitHub don't allow private repositories for free but Visual Studio Online is always private, supports Git and is [free for up to 5 users](https://www.visualstudio.com/en-us/products/visual-studio-online-pricing-vs.aspx). I'm only one person, so great, sounds like the perfect fit. I'll get all the code checked in and branch and get to work then.

Cue montage of trying to get it to work to the theme of Murder She Wrote. I'll let this selection of tweets show you some highlights of the journey:

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">PHP confuses me.</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/564470541428740096">February 8, 2015</a></blockquote>
<script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">On reflection, staying up until 3am messing about with PHP wasn&#39;t the best idea.</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/564623522711236608">February 9, 2015</a></blockquote>

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">Now, can I solve this PHP mystery in 25 minutes before I have to go out? *Cue Mission:Impossible theme*</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/564758186029506561">February 9, 2015</a></blockquote>

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">Computers: the source of my income and maybe also a killing spree in the imminent future.</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/565600543645917184">February 11, 2015</a></blockquote>

## Mission: Failed

As you can see, I gave up. The main problems and reasons being:

  1. The WordPress plug-in was out-of-date (2 years+ since the last update) and didn't work properly with my version of WordPress;
  1. The SQL Server PHP drivers didn't seem to want to run on PHP 5.5. After much banging my head against the wall I discovered that not only did they work fine with PHP 5.4, but also that the PHP drivers were already installed in Azure Websites meaning that lots of playing around trying to find the right binaries and get them to load were pointless;
  1. I could not work out how to import the MySQL data in SQL Server successfully without violating unique constraints and/or losing data integrity.

## A Change Of Tack

After the WordPress/SQL Server debacle I went into hibernation on the whole blog situation for a few months until this week. I'd seen various chatter here and there over the last few months about static site generators, so I wondered whether that would be a viable option. So I issued this tweet:

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">NERDS: recommend me a static site generator. I want to replace a WordPress blog that has fuck-all in it.</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/610525869497565184">June 15, 2015</a></blockquote>

I didn't get my useful feedback from my followers, so I went to our friend Google.  It led me to the following two links:

   1. [Static Site Generators](https://staticsitegenerators.net/) - Just a big list, not very helpful;
   1. [StaticGen](https://www.staticgen.com/) - A much better curated list, ranked by the projects' GitHub star counts.

The main one that lept out was [Jekyll](http://jekyllrb.com/) as I'd heard of it before and GitHub uses it to generate GitHub pages sites. However not long afterwards I discovered that [Jekyll doesn't officially support Windows](http://jekyllrb.com/docs/windows/). I'm mainly a Windows guy, and even though I have an Ubuntu VM in Hyper-V on my laptop, I'd rather stick with the environment and tools I know. There are [guides for getting it to work on Windows](http://jekyll-windows.juthilo.com/), but as my heart wasn't set on Jekyll and I'd rather use something supported, so I ruled it out of the running.

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

So over the course of the next two evenings I set about in earnest migrating over from WordPress to Middleman. It involved a number of steps, and probably took about 10 hours' of effort in total. It was a mixture of migration, feature parity and some new features I fancied adding in because I was giving the blog some love so would be a good opportunity. I hadn't set myself a timescale or deadline for completing the work, but given I only had one extant blog post to worry about, I figured having it completed within a week would be reasonable.

### Installing Ruby + DevKit

Middleman uses some Ruby gems that need the Ruby DevKit so they can compile native extensions. While this is all [documented elsewhere on the internet](http://jekyll-windows.juthilo.com/1-ruby-and-devkit/), I'll just list the basic steps for doing this on Windows here:

  1. [Download](http://rubyinstaller.org/downloads/) and install Ruby (I installed Ruby 2.2.2 (x64));
  1. [Download](http://rubyinstaller.org/downloads/) and install the Ruby Development Kit (I installed the one for Ruby 2.0 (x64));
  1. Run the following commands in a command-line window:

  ```
  ruby dk.rb init
  ruby dk.rb install
  ```

### Setting Up Compilation

I'm quite a big fan of being able to compile on the command-line, so I set myself up a ```Build.cmd``` and checked it into Git for compiling the site as I go. It's nothing ground-breaking, here's the content:

```
@echo off

bundle exec middleman build %*
```

Then it's just a simple command of:

```
c:\coding\blog>build
```

The ```%*``` on the end passes any arguments through to bundle, which is useful if compilation produces errors so you can do this to get more detail:

```
c:\coding\blog>build --verbose
```

### Feature Parity

While WordPress has a plethora of plug-ins and being PHP is infinitely customisable, I never really ventured down that route as PHP isn't my thing. The only thing I'd be missing is a comments engine, but I'll cover that below. That means that all I really need to retain is the same site structure so that anything indexed by search engines doesn't result in a nasty 404. This was pretty much all covered by the examples in the Middleman example templates that got generated and just needed to be tweaked to my liking for how they rendered, like using proper UK date formats.

Below are a few snippets and gotchas found in the process:

#### Dropping .html From URLs

WordPress defaults to extensionless URLs. To keep my URLs I needed to get rid of them.

To do this, set this option on the blog in ```config.rb```:

```
blog.permalink = "{title}"
```

Then *after* the blog options, set this:

```
activate :directory_indexes
```

For a while I had blogs misbehaving but non-blog pages working as expected. This was fixed by turning on ```directory_indexes``` after the blog pages were considered when the directory indexes were created.

#### Forcing CSS and Javascript Cache Updates

If you change your CSS browser caches might not pick-up the change and you'll end up with a screwy UX. If you activate asset hashing as described below, your CSS and Javascript paths (assuming you use the ```javascript_include_tag``` or ```stylesheet_link_tag``` helpers to render them) will have a hash added to the file name so that they move between deployments, forcing client caches to be invalidated.

```
configure :build do
  activate :asset_hash
end
```

#### Fixing Timezone-related Errors

The blog plug-in uses the ```tzinfo``` gem to handle timezones. However Windows doesn't include the data required for this that Linux has natively, which causes builds to fail. The remedy this add this line to ```Gemfile```:

```
gem "tzinfo-data"
```

#### Adding The Homepage To sitemap.xml With The Date Of The Lastest Blog Article

The blog template comes with a ```sitemap.xml.builder``` file which renders a sitemap of articles in the blog for you. It won't render any other pages you might have though. There's probably a snazzier way to enumerate the pages during the build and list everything, but I only wanted two extra entries. To add them manually, you can add something like the code below. This adds an entry for the root of the site, and dates it as being updated at the same time as the latest blog entry (because it lists the last few posts on it).

```
xml.url do
  xml.loc site_url
  xml.lastmod File.mtime(blog.articles[0].source_file).iso8601
  xml.changefreq "monthly"
  xml.priority "0.5"
end
```

For other pages, I change ```<lastmod>``` to be the filetime of the relevant source file:

```
xml.lastmod File.mtime("source/my-page.html.erb").iso8601
```

#### Parameterise All The Things

Put commonly used text, variables etc. as variables in your ```config.rb``` file. For example:

```
set :site_root_uri_canonical, "https://blog.martincostello.com/"
set :blog_author, "Martin Costello"
set :twitter_handle, "martin_costello"
```

I'm unlikely to change my name any time soon, but it makes the code a bit more readable that being hard-coded literals of my name all over the codebase.

#### Partial All The Things

Similar to ASP.NET MVC, as well as full pages Middleman supports partial pages for snippet re-use. I've used this liberally throughout this site to try and keep to the [DRY](https://en.wikipedia.org/wiki/Don%27t_repeat_yourself) principle.

As an example here's the raw code for my entire layout page which contains 7 partials.

```
<!DOCTYPE html>
<html lang="en-gb">
  <%= partial "head" %>
  <body>
    <%= partial "navbar" %>
    <div class="container body-content">
        <%= yield %>
        <hr />
        <%= partial "footer" %>
    </div>
    <%= partial "aside" %>
    <%= partial "scripts" %>
    <%= partial "analytics" %>
    <%= partial "disquscount" %>
    </body>
</html>
```

You can also use partials in partials, which can be, erm, fun.

### Importing Content

I was in the "lucky" situation of only having one extant blog post, so I didn't have to worry about a bulk import or conversion process, which you might not have the luxury of doing. Given this, I just copied the text from the old blog and then manually re-wrote it in Markdown in the new format to live in the new site. That was probably about a 30 minute job and wasn't overly taxing or vexing. There was also the homepage and "about me" pages in the old blog, but I just rewrote those from scratch.

If you need to import content en-masse I'd suggest writing something in your language of choice to try and parse your existing articles and convert the text to Markdown. Alternatively you could just pull out the raw HTML and dump it into a Markdown file as-is and not worry about any dud formatting.

### Syntax Highlighting

My [first blog post](https://blog.martincostello.com/ensuring-your-asp-net-website-is-secure/) uses a lot of code examples and when this blog was still running in its PHP incarnation I was never really happy with the styling.  For this new blog I did some digging around to find something to use. Again, it turns out there's a dedicated Middleman plug-in just for this: ```middleman-syntax```.

You can wire-it up to use [Rouge](https://github.com/jneen/rouge) just like GitHub pages, so that's exactly what I did.

All it took was these lines in the gemfile:

```
gem "middleman-syntax"
gem "redcarpet"
```

And these configuration settings in ```config.rb```:

```
activate :syntax
set :markdown_engine, :redcarpet
set :markdown, :fenced_code_blocks => true
```

### Security

I like to ensure all my sites are best-practice secured, so I ran through the points in my [first blog post](https://blog.martincostello.com/ensuring-your-asp-net-website-is-secure/) and sorted everything out. As there's no code in the site as it's static, this was purely ```Web.config``` jiggery-pokery to get IIS running in Azure doing what I wanted.

### Eye-Candy

As I was doing a relaunch and spruce-up, I figured I'd add a few nice-to-have features to the site to spruce it up. Namely:

  1. Integration with [Disqus](https://disqus.com/) for comments;
  1. Social sharing buttons for Facebook, Google+ and Twitter;
  1. Review my SEO and Social Media related metadata (like OpenGraph ```<meta>``` tags).

I won't get into the detail here as you can see the results here in the blog for yourself, but it was just simple following of the integration guides for Disqus and social sharing and using partials for the buttons and scripts etc. so they were centralised and I could re-use them as needed with minimal fuss.

### Switching Over

With all the feature and testing cards on my VS Online task board in the Done column, it was time to put the site live. Ideally I'd have done it with 100% uptime (just for the show-off factor), but I couldn't due to the need to migrate the SSL host name bindings between Azure Web Apps due to the subscription changeover. For my particular set up this involved:

  1. Remove the host name binding for ```blog.martincostello.com``` from the old Web App;
  1. Add the host name binding for ```blog.martincostello.com``` to the new Web App;
  1. Setup the SSL certificate binding;
  1. Update my DNS ```CNAME``` to point at the new Web App;
  1. Flush my DNS cache.

Then it was just a simple matter of pushing the final build of the site to Azure over FTP, checking it was working and deleting the old Azure resources using the subscription I didn't want to use any more. At which point I felt quite pleased with myself!

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">So long Wordpress.&#10;Bye bye MySQL.&#10;Hello Middleman.</p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/611300636324462592">June 17, 2015</a></blockquote>

## Conclusion

Middleman is a nice static site generator to use for a blog. It has a plug-in for blogging, lets you use Markdown for authoring, has built-in support for paging, sorting, tags and all the other goodness you'd expect in a simple blog. There's a bit of a learning curve if you're not that familiar with Ruby, but unless you want to do something *really* custom, you shouldn't need to learn anything beyond a few loops and string/date formatting. Most of the Ruby you'll write will be tag blocks to output dynamic content during the build process:

```
<%= current_page.data.title %>
```

The biggest selling point for me was being able to use Markdown to write posts. In fact, that's what I'm writing this very post in right now. I've got a few more things left to implement in the near future, such as automating deployment of the ```build``` folder to Azure using Git so I don't need to do manual FTP copies, and adding in a proper HTML sidebar to the pages to list the recent posts and the post archive. Other that that I'm a lot happier with the appearance of my blog, the management overhead, and the cost, than I ever was with the PHP incarnation.

I think this tweet, and the fact that you are reading this blog post right now, speak for themselves:

<blockquote class="twitter-tweet" align="center" lang="en"><p lang="en" dir="ltr">Two evenings of effort and my blog re-architecture is done and Wordpress is in the bin:  <a href="https://t.co/2gD4SGzE4p">https://t.co/2gD4SGzE4p</a></p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/611430850060812290">June 18, 2015</a></blockquote>
