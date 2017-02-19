---
title: Publishing My First Alexa Skill
date: 2017-02-19
tags: alexa,skill,tfl,travis,lambda,aws
layout: bloglayout
description: "Publishing my first Alexa skill using node.js, AWS Lambda, Travis CI and the TfL API."
image: "https://martincostello.azureedge.net/london-travel-512x512.png"
---

A few weeks ago at work it was our quarterly Hackathon. After a dearth of ideas I thought of an idea to extend our Alexa app to encorporate something I've been working on in the office over the last few months. Over the course of a few days a colleague and I tweaked the skill and achieved our aim, which was pretty fun. Did I mention we also won the technical category?

Off the back of that success I thought I'd have a go at writing my own skill, which was [finally accepted into the Alexa Skill Store](https://www.amazon.co.uk/dp/B01NB0T86R "London Travel on amazon.co.uk") on the 14th February after it's third found of certification tests. It's a fairly simple skill with just two "intents" that allows you to either ask about current disruption on any London tube line, the London Overground or the DLR, or for just a specific line. It's also 100% open-source, [hosted on GitHub](https://github.com/martincostello/alexa-london-travel "Alexa London Travel on GitHub").

Now the dust has settled and I've got some free time, I thought I'd do a blog post about how I got started with Alexa and the idea for the skill, how I coded it and set up the Continuous Integration, how I got it through the certification tests and, finally, setting up monitoring for it in production.

READMORE

## Background

Back in November I was lucky enough to travel to Las Vegas for AWS re:Invent through work. Part of the "swag" you got at registration was a free Echo Dot as an incentive to try and get developers to write new skills for the Alexa Skill Store. The idea of the Echo hadn't particularly leapt out at me when I'd seen them advertised, so I hadn't considered buying one myself.  Now I'd acquired an Echo Dot for free I figured I might as well try it out, so I set it up in my living room once I got back from re:Invent. At this point I hadn't considered actually developing any skills for it.

To be honest I mostly used it to set timers and check the weather - nothing too complicated - but I also used it to ask simple questions, much in the same way I used to occasionally ask Siri things with my iPhone. I don't use Siri much anymore as I've found it to not be particularly intelligent for anything beyond setting timers, and unlike Alexa, it isn't really extensible to third-party developers.

Over the last few months I'd also been growing more and more frustrated with the Apple Music app on my iPhone. Most of this frustration came from it deciding to randomly delete songs from storage, opting instead to stream them, even though I don't have an Apple Music subscription. I'd only eventually realised this once my commute to work began involving the tube after moving flats. The worst incidence of this was on a four hour flight back from Greece last summer where it decided to delete all the songs from the phone **while I was listening to it** 30 minutes into the flight. That was a fun rest of the flight with no data or music...

I finally decided I'd have enough of it over Christmas and decided to get a Spotify subscription. I found asking Alexa to drive the Spotify skill really good, with it only once or twice ever playing something I'd not asked for. Admittedly a lot of the intelligence in the Spotify use-case is also the search functionality in Spotify and the songs available, but just asking Alexa to play a song a fancied listening to was a lot more user-friendly than unlocking my phone, opening Spotify, opening search and then typing in what I wanted and clicking on it. I liked the experience of it so much overall I decided to treat myself to a full-sized Echo after Christmas, so now I have the Echo in the living room and the Echo Dot in my bedroom.

I started getting into a routine of asking it about the weather in the morning as I was getting up to find out whether I'd need to dress for wet and/or cold weather. I'd also find myself asking it what was going on with the District Line as I use it to get to the office. Such a request would always result in puzzled response from Alexa as it couldn't find the answer. At this point as I'd not had the Echo for long either, it hadn't really occurred to me to go and find a skill that would do this in the Alexa Skill Store.

That was effectively the seed for the TfL skill idea, and having the Echo and Dot at home had what had given me the inspiration for my Hackathon project. Putting the two things together meant it was now time to dip my toe into the world of app stores...

## Getting started

At a high level, you write a "skill" (effectively an app) by using the [Alexa Skills Kit](https://developer.amazon.com/alexa-skills-kit "Alexa Skills Kit documentation") to receive JSON requests via an Alexa-enabled device (such as the Echo) and repond with JSON containing text. The Alexa-enabled device handles turning the user's request into the JSON request as well as converting your response text into speech for Alexa to respond to the user. The recommended way to do this is via an [Lambda function](http://docs.aws.amazon.com/lambda/latest/dg/welcome.html "AWS Lambda documentation") hosted in Amazon Web Services with an Alexa Custom Skill trigger.

While playing with the Alexa skill at work, I discovered two great resources for building an Alexa skill. [Alexa App](https://github.com/alexa-js/alexa-app "Alexa App on GitHub") and [Alexa App Server](https://github.com/alexa-js/alexa-app-server "Alexa App Server on GitHub"). Because of that I thought I'd go down the same route, and code the skill in node.js using alexa-app.

The [alexa-app npm module](https://www.npmjs.com/package/alexa-app "alexa-app on npm") provides a nice abstraction between processing incoming request JSON via the Alexa Skills Kit AWS Lambda trigger. It provides a way to declare the intents your app handles, as well as the utterances and slot value handling, and maps them to handler functions for handling the intent like the example below.

This effectively means your implementation boils down to some configuration code to wire things together, and then however many handlers you need for your skill's intents. Each handler receives a request and a response, so you can just grab any slot values you want from the request, do whatever logic you need for your skill, and then return text in the response. There's also helpers for returning "cards" for text display of your responses (or links and images) in the Alexa app.

```javascript
function (request, response) {
    var value = request.slot("NUMBER");
    response.say("Your number was " + value ".");
}
```

For local debugging without needing to deploy code for an Alexa-enabled device to use, there's also the [alexa-app-server npm module](https://www.npmjs.com/package/alexa-app-server "alexa-app-server on npm"). This hosts the skill handler using [express](https://www.npmjs.com/package/express "express on npm") and provides a simple web-based UI to select an intent, input any slot values, send a request and view the response JSON with. While this doesn't provide the speech synthesis you can get with the _Test_ tab in the AWS Developer Portal for your skill, it improves the throughput speed of your local code-debug-test loop for quick tweaks and changes.

With these two modules providing a great foundation to start from, it's now time to get the basics of the skill implemented.

For the first version I planned on two intents:

  1. Disruption on the tube, London Overground and DLR generally;
  1. Asking about disruption on a single specific line from those covered generally.

Getting the status of these lines is handled by integrating with the [TfL Unified API](https://api.tfl.gov.uk/ "TfL Unified API"). It's a fairly comprehensive HTTP REST API that returns JSON responses for journey planning, lines, modes of transport etc.  For these two skill intents I only need two of the endpoints:

  1. ```GET /Line/Mode/{modes}/Disruption```
  1. ```GET /Line/{id}/Status```

The first resource drives the overall disruption skill, as I just need to specify the modes I'm interested in (tube, DLR, London Overground), and then parse the response. The second drives the status updates for a specific line. For that one I just need to map the spoken line name from a slot to a line Id. From there it's just a case of using the correct properties in the response document to render the text to convert to speech for Alexa to read out to the user.

Sounds simple enough for now. I coded something basic to start with so I knoew that the API was being invoked and the response body was being used somehow to get a walking skeleton of an implementation. Now I just need to set up deployment.

## Continuous Deployment using Travis CI

As I've decided to open-source the skill, that means I can use [Travis CI](https://travis-ci.org/martincostello/alexa-london-travel "Alexa London Travel's Continuous Deployment on Travis CI") for free to do my Continuous Integration to run my tests. Travis CI also has built-in support for deploying node.js apps to an AWS Lambda function. I'm not using anything fancy for the JavaScript like TypeScript at the moment, so there's no much to the CI - it just needs to run tests in the long term, and out-of-the-box it already runs ```npm install``` and ```npm test``` for you as the standard build script.

Setting up the [YAML file](https://github.com/martincostello/alexa-london-travel/blob/master/.travis.yml "My Travis CI configuration in GitHub") was simple enough, but the first deployment of the lambda function did not work. It turned out this was because [the documentation](https://docs.travis-ci.com/user/deployment/lambda "Travis CI Lambda deployment instructions") to deploy a Lambda was out of date (I need to do a Pull Request to fix it).

The missing bits were:

  * The default node runtime version no longer being supported in AWS.
  * The default handler name being incorrectly generated.
  * The IAM permissions described were insufficient.

The first two were easy to fix, but the third was a bit tricker. The IAM policy provided in the documentation was insufficient to allow Travis to update the Lambda function. After a bit of trial and error, I finally got the below IAM policy to get the deployment working smoothly:

```json
{
  "todo": ""
}
```

**Note**: This policy _may_ be more permissive than it strictly needs to be, but less permissive settings didn't seem to work. If you know what the best IAM policy for this is, let me know and I'll update it. Otherwise, check that the policy meets your needs before using it yourself.

<!--
  * YAML setup, Travis doc out-of-date
  * Tests, mocks, coverage
-->

Once the Lambda deployment was working, I created a ```deploy``` branch and set up Travis to only update the Lambda function for builds on that branch. That way I still get the CI benefit while developing on the master branch, without worrying about updating the lambda unneccessarily.

## Quirks of Alexa's pronounciation

<!--
  * DLR -> Dee El Air -> <say-as></say-as> -> D.L.R.
  * Making it trickier to do card support as display vs. speech are different.
-->

## Getting through the certification process

<!--
  * Verifying the skill Id
  * Leftover test intent in schema
  * Mismatched sample utterances
  * Icon questions
  * Skill launch ended app
  * Help not handled
  * Not handling close and stop and thinking they were the same thing
-->

## Monitoring in production

<!--
  * CloudWatch metrics for slow-startup
  * CloudWatch event to keep the lambda "warm"
  * Increasing max execution to 10s
-->

## Possible future changes

<!--
  * Account linking for favourite lines
  * Improve speech synthesis.
  * https://echosim.io/
-->
