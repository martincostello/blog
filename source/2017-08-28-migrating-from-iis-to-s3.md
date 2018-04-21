---
title: Migrating to Amazon S3
date: 2017-08-28
tags: aws,cloudfront,lambda,lambda@edge,s3
layout: bloglayout
description: "Migrating a static website from being hosted with IIS in Azure to S3, CloudFront and Lambda@Edge in AWS."
image: "https://cdn.martincostello.com/blog_amazon-simple-storage-service.png"
---

Since I set up my blog in March 2014, it's been running in IIS as part of an Azure App Service. Initially this was required as the blog was originally a WordPress site, so a server was required to run the PHP code for WordPress. However when I got fed up with keeping WordPress up-to-date and [migrated to a static Middleman site](/why-i-switched-from-wordpress-to-middleman/ "Why I Switched From WordPress To Middleman"), I left it hosted in Azure. This was mainly because it was the easiest option, as that's where it was already, but also because this allowed me to specify HTTP response headers still, such as for `X-Frame-Options`.

At the end of the day though, this blog is still statically generated, and running a whole web server (actually two, one in Azure's East US datacentre, another in UK South) is just overkill. Given that Amazon S3 supports static website hosting, I thought I'd migrate it to an S3 bucket instead.

READMORE

## The Starting Point

Things were already in a fairly good starting point for the migration. The code is all in [GitHub](https://github.com/martincostello/blog "Blog source code on GitHub"), there is already a Continuous Integration set up in [Travis CI](https://travis-ci.org/martincostello/blog "Travis CI build"), and I'd already had some practice setting up a static site in S3 when I recently created [my first React app](https://github.com/martincostello/credit-card-splitter "Credit Card Splitter"). This meant I already had the infrastructure ready to easily start the migration, and I'd already practiced some of the mechanics of getting a static site running from a S3 bucket accessed through CloudFront.

## Migrating the Static Content

So to start I needed an S3 bucket. I had actually already created an S3 bucket last year after I found out that your bucket has to have the _exact_ same name as your DNS hostname for the website you want to host, so I wanted to make sure it belonged to me. However, when I'd set it up I'd set it up in the wrong region. No biggie, I'll just delete it and recreate it right? Almost.

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_one-does-not-simply-recreate-an-s3-bucket.jpg" alt="One does not simply recreate an S3 bucket" title="One does not simply recreate an S3 bucket">

Turns out that because S3 bucket names are global and because S3 is a distributed system, **fully** deleting an S3 bucket actually takes a non-trivial amount of time. Once I deleted the bucket from the AWS Console, I tried to recreate it in a new region. This failed immediately in the wizard for the first minute or so because it said that a DNS entry for the bucket already existed. Once that error resolved itself I could continue through the wizard to set things up, but it would then fail on the final step with the error:

<blockquote class="blockquote">
  <p class="mb-0">
    A conflicting conditional operation is currently in progress against this resource. Please try again.
  </p>
</blockquote>

What? A quick search turns up this [answer on StackOverflow](https://stackoverflow.com/a/16553056/1064169 "Answer on StackOverflow for the error message"). Turns out I just need to wait. For an hour.

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_homer-simpson-chair-goes-round.gif" alt="Chair goes round, chair goes round..." title="Chair goes round, chair goes round...">

With the S3 bucket effectively moved, it needs configuring to enable Static website hosting with the appropriate index and error pages.

Once I've got the S3 bucket created in the correct region, I could press on. The steps were fairly simple:

  1. Add a deployment section to the `.travis.yml` file to copy the static content for the site to S3: [configuration].(https://github.com/martincostello/blog/blob/5db3f6495e8accaaa567171b871a225c4e0f8d57/.travis.yml#L18-L28 "S3 deployment configuration in GitHub")
  1. Copy my TLS certificate to Amazon Certificate Manager in `us-east-1`.
  1. Set up a new CloudFront web distribution to serve the content from the S3 bucket (more waiting) with a CNAME for `blog.martincostello.com`.
  1. Configure a custom 404 error page for the distribution.

## Migrating the HTTP Response Headers

As the blog is being moved away from IIS, that means I can't set HTTP response headers anymore. Well, I could if I set them manually for _every_ HTML page when uploaded to S3 by Travis CI, but that's a whole lot of faff.

Fortunately, there's another way to solve this problem.

**Lambda@Edge**, [announced at AWS re:Invent 2016](https://twitter.com/AWSreInvent/status/804394916952408064 "Lambda@Edge launch announcement") and made [generally available in July 2017](https://aws.amazon.com/blogs/aws/lambdaedge-intelligent-processing-of-http-requests-at-the-edge/ "AWS Lambda@Edge GA announcement blog post"), allows you to run code in your CloudFront edge locations in any of four parts of the request path; viewer request and response, and origin request and response. Using a lightweight Lambda function, such as with Node.js, you can run small code snippets that change the behaviour of requests through CloudFront to suit your needs.

For setting HTTP response headers, this just requires me to add a lambda that runs for `viewer-response` in CloudFront.

Setting this up required:

  1. Writing a Node.js function to set the HTTP response headers: [code](https://github.com/martincostello/blog/blob/130cdf59633cadac1a1722979757d0b7aadc2768/cloudfront-headers.js "Lambda function to set the HTTP response headers").
  1. Adding a deployment section to the `.travis.yml` file to deploy the lambda function to set the response headers: [configuration](https://github.com/martincostello/blog/blob/5db3f6495e8accaaa567171b871a225c4e0f8d57/.travis.yml#L43-L56 "Lambda deployment configuration in GitHub").
  1. Updating the IAM policy for the user associated with the AWS access keys Travis CI uses to give it permission to create the new lambda function (I have this locked-down per-function, rather than on a wildcard).

While it's possible to create a lambda directly from a Travis CI deployment, the IAM role required for a Lambda@Edge function is slightly different as it requires a slightly different trust relationship for the Edge execution. The easiest way to set this up was to use the AWS Console to create it with the `cloudfront-modify-response-header` template, which can automatically create the correct execution role for you. The policy documents for the role the console created are below.

One gotcha with Lambda@Edge though, is the way it handles deployment. The functions run in CloudFront are actually replicas of your function, and the `$LATEST` version is not used. You have to publish a new **numbered** version of your Lambda function, _and_ set up the trigger again manually for the new version you wish to use. Once you associate the trigger with the numbered version, it is _replicated_ to your CloudFront distribution within a few minutes and begins execution on each request.

I hope that this is something AWS improve the developer experience for in the future so that it's easy to deploy on a rolling basis like the static content is to S3 from Travis CI, rather than something requiring continual manual intervention.

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_lambda-edge-function-trigger.png" alt="Lambda@Edge function trigger" title="Lambda@Edge function trigger">

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_lambda-edge-function-replica.png" alt="Lambda@Edge function replica" title="Lambda@Edge function replica">

### Permissions Policy

```
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "logs:CreateLogGroup",
        "logs:CreateLogStream",
        "logs:PutLogEvents"
      ],
      "Resource": [
        "arn:aws:logs:*:*:*"
      ]
    }
  ]
}
```

### Trust Relationship

```
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": {
        "Service": [
          "lambda.amazonaws.com",
          "edgelambda.amazonaws.com"
        ]
      },
      "Action": "sts:AssumeRole"
    }
  ]
}
```

## Deployed(?)

With both the static and dynamic parts of the blog set up in AWS, it was time to update the CNAME for my blog to point to the CloudFront distribution's hostname instead of the existing Azure Traffic Manager profile.

Unfortunately, this wasn't quite as simple as it first seemed...

### Problem 1 - Jumping the Gun

I updated my DNS, flushed my DNS host cache and loaded the blog...and got a security warning in Chrome saying that the TLS certificate didn't match the hostname.

I double checked the TLS configuration in CloudFront and everything was correct, so I opened the [Qualys SSL Server Test](https://www.ssllabs.com/ssltest/index.html "Qualys SSL Server Test") and pointed it at the blog. Looking right at the bottom of the test results I could see that instead of an HTTP 200 from `blog.martincostello.com`, I was getting an HTTP 307 from the CloudFront hostname. This explains the warning, but doesn't directly give away the root cause. Some Googling leads to [this StackOverflow answer](https://stackoverflow.com/a/38708364/1064169 "HTTP 307 from CloudFront with S3 origin"), which effectively says that this occurs when the distribution is still provisioning.

I'd waited until the status in the AWS Console had changed to "Deployed" before changing over the DNS so I was a bit confused. I viewed the distribution in the console again, and it showed as "In Progress". I gave it a few minutes and then it changed to "Deployed" again. Re-running the Qualys test showed things were OK.

The lesson here is to not be too eager to use a newly created CloudFront distribution - give it a bit of time to settle before starting to request things from it.

### Problem 2 - Why Aren't the Posts Loading?

Once the TLS issue was resolved, the homepage of the blog appeared, being served from S3. I check the response headers, and they're being returned as expected from the Lambda@Edge function, so everything looks like it's complete. Then I click through to a post to just do a sanity check...and I get an XML access denied message. This is unexpected for two reasons:

  1. Where is the post?
  1. Where is the custom error page?

It looks like the post files are missing, so maybe they weren't uploaded to S3? I check the bucket in the AWS Console, and the files are there, but also they aren't.

It turns out that I'd forgotten a behaviour of IIS that was silently powering the blog's URL layout. The posts in this blog have paths like `migrating-from-iis-to-s3` to make them SEO-friendly, but on-disk those fies don't actually exist, it's a folder. The content is actually at `migrating-from-iis-to-s3/index.html` as generated by Middleman, but the default IIS static file serving behaviour is to serve the content of `index.html` from a subdirectory if the path is a folder.

S3 only exhibits this behaviour for the root path of the S3 bucket, serving a pre-configured file (usually `index.html`), but this does not apply to subdirectories.

The solution I came up with to this is effectively the same as the one for HTTP response headers. To fix this I added a second Lambda@Edge function that runs for `origin-request`. The lambda checks the path of the URL requested, and if it is for an extensionless file (such as `migrating-from-iis-to-s3`), then it updates the URL to fetch from the origin to be suffixed with `/index.html`, thus becoming `migrating-from-iis-to-s3/index.html`.

All I needed to do was [add the function code](https://github.com/martincostello/blog/blob/65aaa0be644ac3a39829c295dfe73dfe6ccfb577/cloudfront-folders.js "function to fix folder paths"), [add the deployment configuration](https://github.com/martincostello/blog/blob/5db3f6495e8accaaa567171b871a225c4e0f8d57/.travis.yml#L29-L42 "Travis CI configuration") and manually set up the CloudFront trigger again.

For the custom error page, it turns out that at first it was broken by the same subfolder problem the lambda fixed, but once that was resolved it showed that it was also misconfigured due to a misunderstanding of the S3 behaviour.

To prevent information disclosure from enumerating the S3 host, any files that do not exist always return an HTTP 403 instead of an HTTP 404 so that an unauthorised user cannot tell the different between a file that does not exist and a file that exists but they do not have access to.

Fixing the error pages just required adding an additional CloudFront error page to handle HTTP 403 from the origin as HTTP 404 to the viewer using the 404 page from the origin as the response content instead.

## Done and Done

So with some ad-hoc fixes for bugs deployed to both S3 and Lambda@Edge, the migration to S3 is complete. I learned a few things by doing along the way, like allowing new CloudFront distributions to settle before using them, leveraged some new AWS functionality, namely Lambda@Edge, and saved some cost and resource in Azure.

I've run some tests with [Qualys](https://www.ssllabs.com/ssltest/index.html "Qualys SSL Server Test"), [Pingdom](https://tools.pingdom.com/ "Pingdom Website Speed Test"), [PageSpeed](https://developers.google.com/speed/pagespeed/insights/ "Google PageSpeed Insights") and [securityheaders.io](https://securityheaders.io/ "securityheaders.io"), and everything's looking good. With that I've now been able to delete the old Azure App Services and Traffic Manager profile, which gives me back some memory and CPU for other sites running on the same App Service Plans (such as [the site my for my Alexa skill](https://londontravel.martincostello.com/ "London Travel skill website")).

I think if I were to do this again, I'd also do more testing on the migrated website from the CloudFront hostname first, before switching over the CNAME record in DNSimple. Don't fool yourself into thinking you don't need to do much testing because there's no code involved because it's a static site - infrastructure also needs testing!

Of course, now I should probably blog a bit more often to justify this S3 migration...
