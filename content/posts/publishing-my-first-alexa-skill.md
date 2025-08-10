---
title: Publishing My First Alexa Skill
date: 2017-02-20
tags: alexa,skill,tfl,travis,lambda,aws
layout: post
description: "Publishing my first Alexa skill using node.js, AWS Lambda, Travis CI and the TfL API."
image: "https://cdn.martincostello.com/london-travel-108x108.png"
---

A few weeks ago at work it was our quarterly Hackathon. After a dearth of ideas I thought of an idea to extend our Alexa app to incorporate something I've been working on in the office over the last few months. Over the course of a few days a colleague and I tweaked the skill and achieved our aim, which was pretty fun. Did I mention we also won the technical category?

Off the back of that success I thought I'd have a go at writing my own skill, which was [finally accepted into the Alexa Skill Store](https://www.amazon.co.uk/dp/B01NB0T86R "London Travel on amazon.co.uk") on the 14th February after it's third round of certification tests. It's a fairly simple skill with just two "intents" that allows you to either ask about current disruption on any London tube line, the London Overground or the DLR, or for just a specific line. It's also 100% open-source, [hosted on GitHub](https://github.com/martincostello/alexa-london-travel "Alexa London Travel on GitHub"). The [free AWS Lambda tier](https://aws.amazon.com/lambda/pricing/#lambda "AWS Lambda pricing") also makes it free to run (unless the skill becomes wildly popular...).

Now the dust has settled and I've got some free time, I thought I'd do a blog post about how I got started with Alexa and the idea for the skill, how I coded it and set up the Continuous Integration, how I got it through the certification tests and, finally, setting up monitoring for it in production.

<!--more-->

## Background

Back in November I was lucky enough to travel to Las Vegas for AWS re:Invent through work. Part of the "swag" you got at registration was a free Echo Dot as an incentive to try and get developers to write new skills for the Alexa Skill Store. The idea of the Echo hadn't particularly leapt out at me when I'd seen them advertised, so I hadn't considered buying one myself.  Now I'd acquired an Echo Dot for free I figured I might as well try it out, so I set it up in my living room once I got back. At this point I hadn't considered actually developing any skills for it.

To be honest I mostly used it to set timers and check the weather - nothing too complicated - but I also used it to ask simple questions; much in the same way I used to occasionally ask Siri things with my iPhone. I don't use Siri much anymore as I've found it to not be particularly intelligent for anything beyond setting timers and, unlike Alexa, it isn't really extensible to third-party developers.

Over the last few months I'd also been growing more and more frustrated with the Apple Music app on my iPhone. Most of this frustration came from it deciding to randomly delete songs from storage, opting instead to stream them, even though I don't have an Apple Music subscription. I'd only eventually realised this once my commute to work began involving the tube after moving flats. The worst incidence of this was on a four hour flight back from Greece last summer where it decided to delete all the songs from the phone **while I was listening to it** 30 minutes into the flight. That was a fun rest of the flight with no data _or_ music...

I finally decided I'd had enough of it over Christmas and decided to get a Spotify subscription. I found asking Alexa to drive the Spotify skill really good, with it only once or twice ever playing something I'd not asked for. Admittedly a lot of the intelligence in the Spotify use-case is also the search functionality in Spotify and the songs available, but just asking Alexa to play a song a fancied listening to was a lot more user-friendly than unlocking my phone, opening Spotify, opening search and then typing in what I wanted and clicking on it. I liked the experience of it so much overall I decided to treat myself to a full-sized Echo after Christmas, so now I have an Echo in the living room and the Echo Dot in my bedroom.

I started getting into a routine of asking it about the weather in the morning as I was getting up to find out whether I'd need to dress for wet and/or cold weather. I'd also find myself asking it what was going on with the District Line as I use it to get to the office. Such a request would always result in puzzled response from Alexa as it couldn't find the answer. At this point as I'd not had the Echo for long either, so it hadn't really occurred to me to go and find a skill that would do this in the Alexa Skill Store.

That was effectively the seed for the TfL skill idea, and having the Echo and Echo Dot at home had given me the inspiration for my Hackathon project. Putting the two things together meant it was now time to dip my toe into the world of app stores...

## Getting started

At a high level, you write a "skill" (effectively an app) by using the [Alexa Skills Kit](https://developer.amazon.com/alexa-skills-kit "Alexa Skills Kit documentation") to receive JSON requests via an Alexa-enabled device (such as the Echo) and respond with JSON containing text. The Alexa-enabled device handles turning the user's request into the JSON request as well as converting your response text into speech for Alexa to respond to the user. The recommended way to do this is via a [Lambda function](http://docs.aws.amazon.com/lambda/latest/dg/welcome.html "AWS Lambda documentation") hosted in Amazon Web Services with an Alexa Custom Skill trigger.

While playing with the Alexa skill at work, I discovered two great resources for building an Alexa skill. [Alexa App](https://github.com/alexa-js/alexa-app "Alexa App on GitHub") and [Alexa App Server](https://github.com/alexa-js/alexa-app-server "Alexa App Server on GitHub"). Because of that I thought I'd go down the same route, and code the skill in node.js using alexa-app.

The [alexa-app npm module](https://www.npmjs.com/package/alexa-app "alexa-app on npm") provides a nice abstraction between processing incoming request JSON via the Alexa Skills Kit AWS Lambda trigger. It provides a way to declare the intents your skill handles, as well as the utterances and slot value handling, and maps them to handler functions for processing the intent like the example below.

This effectively means your implementation boils down to some configuration code to wire things together, and then however many handlers you need for your skill's intents. Each handler receives a request and a response, so you can just grab any slot values you want from the request, do whatever logic you need for your skill, and then return text in the response. There's also helpers for returning "cards" for text display of your responses (or links and images) in the Alexa app.

```javascript
function (request, response) {
    var value = request.slot("NUMBER");
    response.say("Your number was " + value ".");
}
```

This simple input-output pairing also allowed for easy unit testing later on, plus the API can be easily integration tested by providing full JSON Alexa requests for high-code coverage and to test intent-to-handler mappings.

For local debugging without needing to deploy code for an Alexa-enabled device to use, there's also the [alexa-app-server npm module](https://www.npmjs.com/package/alexa-app-server "alexa-app-server on npm"). This hosts the skill handler using [express](https://expressjs.com/ "The express website") and provides a simple web-based UI to select an intent, input any slot values, send a request and view the response JSON with. While this doesn't provide the speech synthesis you can get with the _Test_ tab in the AWS Developer Portal for your skill, it improves the throughput speed of your local code-debug-test loop for quick tweaks and changes. It also renders the skill intent schema for you for pasting into the skill's interaction model in the developer portal.

With these two modules providing a great foundation to start from, it's now time to get the basics of the skill implemented.

For the first version I planned on two intents:

  1. Disruption on the tube, London Overground and DLR generally;
  1. Asking about disruption on a single specific line from those covered.

Getting the status of these lines is handled by integrating with the [TfL Unified API](https://api.tfl.gov.uk/ "TfL Unified API documentation"). It's a fairly comprehensive HTTP REST API that returns JSON responses for journey planning, lines, modes of transport etc. and is used to drive many of TfL's own applications and services.  For these two skill intents I only need to use two of the API's resources:

  1. `GET /Line/Mode/{modes}/Disruption`
  1. `GET /Line/{id}/Status`

The first resource drives the overall disruption skill, as I just need to specify the modes I'm interested in (tube, DLR, London Overground), and then parse the response. The second drives the status updates for a specific line. For that one I just need to map the spoken line name from a slot to a line Id. From there it's just a case of using the correct properties in the response document to render the text to convert to speech for Alexa to read out to the user.

Sounds simple enough for now. I coded something basic to start with so I knew that the API was being invoked and the response body was being used somehow to get a walking skeleton of an implementation. Next I needed to set up deployment to AWS Lambda to use it on my Echo Dot.

## Continuous Deployment using Travis CI

As I've decided to open-source the skill, that means I can use [Travis CI](https://travis-ci.org/martincostello/alexa-london-travel "Alexa London Travel's Continuous Deployment on Travis CI") for free to do my Continuous Integration to run my tests. Travis CI also has built-in support for deploying node.js apps to an AWS Lambda function. I'm not using anything fancy for the JavaScript like TypeScript at the moment, so there's no much to the CI - it just needs to run tests in the long term, and out-of-the-box it already runs `npm install` and `npm test` for you as the standard build script.

Setting up the [YAML file](https://github.com/martincostello/alexa-london-travel/blob/main/.travis.yml "My Travis CI configuration in GitHub") was simple enough, but the first deployment of the lambda function did not work. It turned out this was because [the documentation](https://docs.travis-ci.com/user/deployment/lambda "Travis CI Lambda deployment instructions") to deploy a Lambda was out of date (I need to do a Pull Request to fix it. **_Updated 20/02/2017: [Pull Request](https://github.com/travis-ci/docs-travis-ci-com/pull/974 "GitHub Pull Request to update the Travis CI Lambda deployment documentation")_**).

The missing bits were:

- The default node runtime version no longer being supported in AWS.
- The default handler name being incorrectly generated.
- The IAM permissions described were insufficient.

The first two were easy to fix, but the third was a bit trickier. The IAM policy provided in the documentation was insufficient to allow Travis to update the Lambda function. After a bit of trial and error, I finally used the below IAM policy to get the deployment working smoothly:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Sid": "ListExistingRolesAndPolicies",
      "Effect": "Allow",
      "Action": [
        "iam:ListRolePolicies",
        "iam:ListRoles"
      ],
      "Resource": "*"
    },
    {
      "Sid": "CreateAndListFunctions",
      "Effect": "Allow",
      "Action": [
        "lambda:CreateFunction",
        "lambda:ListFunctions"
      ],
      "Resource": "*"
    },
    {
      "Sid": "DeployCode",
      "Effect": "Allow",
      "Action": [
        "lambda:UploadFunction",
        "lambda:UpdateFunctionCode",
        "lambda:UpdateFunctionConfiguration"
      ],
      "Resource": "arn:aws:lambda:{region}:{account-id}:function:{function-name}"
    },
    {
      "Sid": "SetRole",
      "Effect": "Allow",
      "Action": [
        "iam:PassRole"
      ],
      "Resource": "arn:aws:iam::{account-id}:role/{role-name}"
    }
  ]
}
```

**Note**: This policy _may_ be more permissive than it strictly needs to be, but less permissive settings didn't seem to work. If you know what the best IAM policy for this is, let me know and I'll update it. Otherwise, check that the policy meets your needs before using it yourself.

_**Updated 20/02/2017**: The IAM policy has now been updated with the minimum permissions to deploy successfully from Travis CI._

Once the Lambda deployment was working, I created a `deploy` branch and set up Travis to only update the Lambda function for builds on that branch. That way I still get the CI benefit while developing on the main branch, without worrying about updating the lambda unnecessarily.

## Quirks of Alexa's pronunciation

As I further refined the skill implementation I found that Alexa didn't pronounce "DLR" well. In theory the documentation for Alexa says that capitalised words can be spelled out, but Alexa seemed to think "DLR" was a word and pronounced it in an odd way. Fortunately Alexa supports something known as [Speech Synthesis Markup Language (SSML)](https://www.w3.org/TR/speech-synthesis/ "SSML W3C recommendation"). This allows you to use an XML-like syntax to describe metadata for how words should be pronounced.

However it wasn't quite that simple. The documentation allows you to [specify that a word should be spelled out](https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/speech-synthesis-markup-language-ssml-reference#say-as "SSML documentation for Alexa") using a syntax like this:

```xml
<say-as interpret-as="spell-out">DLR</say-as>
```

Yet, that didn't work - it just made the weirdness of the pronunciation...different.

<blockquote class="twitter-tweet" data-lang="en"><p lang="en" dir="ltr">Before: <a href="https://t.co/CgZTmB78oX">pic.twitter.com/CgZTmB78oX</a></p>&mdash; Martin Costello (@martin_costello) <a href="https://twitter.com/martin_costello/status/827548751829094400">February 3, 2017</a></blockquote>
<script async src="//platform.twitter.com/widgets.js" charset="utf-8"></script>

Using the speech preview in the _Test_ tab of the skill in the developer portal allowed for some trial and error though. Eventually I got it being pronounced correctly by placing dots between the letters: `D.L.R.`. This had the unintended side-effect later of making [cards](https://developer.amazon.com/public/solutions/alexa/alexa-skills-kit/docs/providing-home-cards-for-the-amazon-alexa-app "Alexa cards documentation") slightly more difficult to implement. This was because my modified text for the speech could no longer just be returned as-is for use in the cards as it would not look right when read. This just required some extra code to put the raw TfL API text responses into the cards, and then post-process them to adjust the pronunciation.

## Getting through the certification process

Once I was happy with the implementation of my skill through testing the lambda myself by speaking to the skill installed on my Echo Dot, I submitted it for certification. Ultimately it took three submissions to get through the skill certification process tests, with each attempt taking approximately 48 hours to get the results via email after being submitted. The failures were a mixture of bugs, mistakes, not reading the test cases properly in advance, and my graphic design skills. Below is a summary of what failed and how I fixed it.

### Attempt 1

  1. [An intent that didn't work](https://github.com/martincostello/alexa-london-travel/issues/12) - I'd initially had a third "test" intent for checking connectivity to the TfL API that I used when initially deploying the lambda function. I'd removed this before certification, but forgotten to remove it from the skill's intent schema. The certification process seems to involve a lot of automated tests, and as the tests read the schema, they failed the intent as "unresponsive". This was easily fixed by correcting the skill schema.
  1. [Incorrect utterances](https://github.com/martincostello/alexa-london-travel/issues/13) - I'd added some sample utterances to the skill metadata in the developer portal, but they weren't in the utterance list specified in the interaction model. Again, this was easy to correct.
  1. [Not launching correctly](https://github.com/martincostello/alexa-london-travel/issues/15) - If the skill was launched with no intent (for example, "_Alexa, open London Travel_"), the skill would just play a greeting and then exit. This meant that all utterances had to start with "_Alexa ask London Travel..._", which is not an optimum user experience. This just needed a [one-line tweak](https://github.com/martincostello/alexa-london-travel/commit/9daba4a52daeedb23472fbb3ea27fa5feb1d24ca "the fix") to the response to not end the session.
  1. [An unclear icon](https://github.com/martincostello/alexa-london-travel/issues/14) - My graphic design skills are effectively non-existent, so I just made a 4x4 grid of square coloured according to the colours used by TfL for the lines supported by the skill. The icon I designed was initially rejected as "unclear or misleading" but without any specific detail about that. As I wasn't confident on designing a new icon without knowing what was wrong with the old one, I queried this with the certification team, explaining the symbolism. Eventually I received a reply saying the icon was fine. I wonder if it was initially rejected because it evokes memories of [Elmer the elephant](http://www.andersenpress.co.uk/elmer/ "Elmer the elephant's website") as some people pointed out to me after I'd made it...

### Attempt 2

  1. [Not responding to "_help_"](https://github.com/martincostello/alexa-london-travel/issues/16) - The skill had no functionality to ask it for help, which was easily added by handling the built-in `AMAZON.HelpIntent` intent.
  1. [Not responding to "_cancel_" or "_stop_"](https://github.com/martincostello/alexa-london-travel/issues/17) - The incorrect launch functionality in the previous submission had hidden that the skill could not be asked to stop or cancel if it was idle. This was also fixed by using the built-in `AMAZON.CancelIntent` and `AMAZON.StopIntent` intents. Initially I thought these were handled as the same intent, until discovering they were distinct during testing with the Echo Dot.

### Attempt 3

Certified and made available on [amazon.co.uk](https://www.amazon.co.uk/dp/B01NB0T86R "London Travel on amazon.co.uk")!

## Monitoring in production

Once the skill was published to the Alexa Skill store, I found that on the second morning the skill would not respond when I invoked it. The blue ring on my Echo Dot would spin for 3 seconds and then Alexa would tell me the skill had failed to respond correctly. The lambda appeared to have "gone to sleep" from not being used overnight, and as it was taking more than 3 seconds to start-up (the default response timeout), the requests were all failing.

I resolved the "keeping the skill warm" (or at least I thought I did) by setting up a [CloudWatch Event](http://docs.aws.amazon.com/AmazonCloudWatch/latest/events/WhatIsCloudWatchEvents.html "CloudWatch events documentation") to make a request to the lambda function to launch the skill every 5 minutes. I then set up CloudWatch alarms to watch for errors, throttled requests, and requests that took too long.

The "keep it warm event" doesn't seem to fully solve the laggy responses though, as two days later I had the same problem again despite no CloudWatch alarms having gone off. I've worked around this for now by changing the maximum lambda request timeout to 10 seconds. This gives the function plenty of time to start-up if it does "go cold". I need to do further research into finding out why the event isn't enough to keep the function warm.

## Wrapping up

In a future version I might look into enabling Amazon account-linking to allow users to use a companion website to save their favourite lines to allow for a personalised commute status, as well as some further "speech massage" of the responses from TFL. For example, the acronym "_SWT_" for "_South West Trains_" sounds a bit weird when it appears in statuses. Feature requests and bug reports are gratefully accepted on [GitHub](https://github.com/martincostello/alexa-london-travel/issues "Alexa London Travel issues on GitHub").

That's about it really. All-in-all it took a couple of days of effort and about three weeks of elapsed time to go from the first commit to a published skill, mostly done on days off. It was fun to work on, and has given me a bit of experience playing with not only Alexa, but AWS Lambda and node.js.

I hope you've found this post interesting or useful. If you've read this far, how about installing the skill or leaving a review on [amazon.co.uk](https://www.amazon.co.uk/dp/B01NB0T86R "")? :)
