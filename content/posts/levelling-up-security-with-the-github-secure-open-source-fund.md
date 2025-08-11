---
title: "Levelling Up Security with the GitHub Secure Open Source Fund"
date: 2025-08-11
tags: github,open source,polly,security,sponsorship
layout: post
description: "How participating in the GitHub Secure Open Source Fund has levelled-up security for my open source projects."
cdnImage: "github-sosf.png"
---

{{< cdn-image path="github-sosf.png" title="The GitHub Secure Open Source Fund, showing the logos of various companies including Microsoft, 1Password, American Express, Shopify, Stripe and Vercel" >}}

After sitting on the secret for the last few months, I'm excited to finally share that back in May I was selected to participate
in the second cohort of the [GitHub Secure Open Source Fund][github-sosf-session-2] through my maintenance of [Polly][polly]. 🎉

The [GitHub Secure Open Source Fund][github-sosf] is a GitHub-lead initiative aimed at improving the security of open source software
at scale by providing funding and resources to maintainers of popular open source projects. Big names in the software industry like
1Password, American Express, Shopify, Stripe, and Vercel help fund this initiative to enhance the security posture of open source
software projects across many language ecosystems.

Many open source projects are fundamental building blocks of countless software applications, whether open source or proprietary, and
the security of the entire software supply chain can be at risk if security vulnerabilities occur in these community-driven projects.
For example, Polly is a dependency of [.NET Aspire][aspire], which has become quite popular over the last year.

Through my participation in the GitHub Secure Open Source Fund, I gained access to a wealth of valuable resources, including expert
guidance from GitHub staff and experts on best practices for securing open source software, as well as funding to help sustain my
involvement in the development and maintenance of the many open source projects I contribute to.

<!--more-->

Participating in the program has required a non-trivial time commitment over the last few months, but the things I have learned and
the connections I have made with other participants in the open source community have been invaluable.

In this blog post, I will share my experiences and insights gained from the program, as well as some of the concrete outcomes from my
participation that I've been able apply not just to Polly, but for other projects I contribute to as well.

## The Application Process

I'd heard about the GitHub Secure Open Source Fund when it was [first announced][github-sosf-annoucement] in November 2024, but at the
time I didn't think of myself as "worthy" of such an opportunity. Maybe this was just the [Impostor Syndrome][impostor-syndrome] talking,
but the projects I was used to seeing participating in things such as this were typically really popular and famous projects written in
languages such as JavaScript, Go, or Python. C# doesn't usually appear among the ranks of the participants for these sort of initiatives.
I thought it was interesting to hear about, but I didn't think much about it further.

Fast forward to late March 2025, and I travelled to Seattle to attend the [2025 Microsoft MVP Summit][mvp-summit-2025] as part of being
a [Microsoft MVP myself][mvp]. One evening at a social mixer I was chatting to [Lotte Pitcher][lotte-pitcher], a fellow Microsoft MVP
from Umbraco, who said they'd been to a session that day from GitHub that brought up the GitHub Secure Open Source Fund. After some
chatting, they introduced me to [Kevin Crosby][kevin-crosby] from GitHub, where we discussed the program in more detail. We talked about
the program's goals and how my work on open source projects like Polly and [Swashbuckle.AspNetCore][swashbuckle] sounded like they were
a good fit, and they encouraged me to apply.

Once I returned home, I reflected on it some more and decided to apply. The process involved filling out an application, providing
details about my work on open source projects, an interview with [Gregg Cochran][gregg-cochran] who leads the program, as well as
recording a 45 second video pitch for the project selection committee 🎬. The latter was certainly a novel experience for me!

After submitting all the necessary paperwork, I was thrilled to be accepted into the program to participate in the workshops starting in June.

## The Workshops

The workshops are the core component of the program, providing participants with the opportunity to learn from GitHub experts and
improve their knowledge of security best practices. I've had experience to this sort of training before, but always as part of a
mandatory corporate security training program.

Here the focus was on the topic through the lens of open source projects and the unique challenges they face. This made the material
much more relevant and engaging for me, compared to being told what an SQL Injection attack is for the umpteenth time.

The workshops involved participants from all over the world from dozens of different open source projects. It was quite humbling to be
participating in the same sessions as people working on __extremely__ well-known projects such as [Bootstrap][bootstrap] (which I use
extensively), [Express.js][expressjs] and [JUnit][junit], amongst many other equally deserving projects. Sessions were run at 4pm UK
time, which was convenient for me as I could shift my working day earlier on the two days a week the sessions were held and switch
from one laptop to another to join the Zoom calls.

The sessions covered a diverse set of topics over a three week period, delivered as either talks or interactive workshops. Over the
course of my participation, I attended 16 different sessions on a diverse set of security-related topics. Content we covered included:

- 📄 How to comply with Open Source licensing;
- 🚨 How to respond to security incidents;
- 🔍 Threat modeling;
- 🔒 Secure UX;
- 🐻 [Fuzzing][fuzzing].

There were also some drop-ins from senior Microsoft and GitHub leaders too.

While tiring at times due to it creating some long days in front of a computer (particularly in the summer ☀️), it was really fun to
participate in the program and learn from other maintainers in the open source community, regardless of the technologies they use to
implement them or whether I use their projects myself.

## The Learnings

So did I learn anything new from my participation in the GitHub Secure Open Source Fund? Absolutely!

I've done security training modules for software before, but never with a focus on open source projects and their unique challenges.
While the sessions may have covered topics I was already familar with, such as use of [CodeQL][codeql], the context and application
to open source provided a significant levelling up in my understanding of these areas.

We were encouraged to make notes as the program went along, and reviewing them now as I write this blog post, there's a number of items
I thought it would be worth mentioning here.

### License Compatibility

While you might use an [OSI Approved License][osi-licences] for your open source project (e.g. Apache 2.0), it's important to check
that _all_ the dependencies and components you use and distribute to others via your project are also compatible with that license.
Including a dependency that uses a license that isn't compatible with closed source software, for example, could limit the number of
people who can use your project. It's important to understand the disctinction between "open source" and "source open".

Tools such as [GitHub's dependency-review-action][dependency-review-action] can help automate the process of checking for license
compatibility across your project's dependencies to help ensure that you do not accidentally add a dependency with an incompatible license.

### Secure UX Design

While security in software often focuses on the code and dependencies of a project, an often overlooked aspect is how to embed
security into the user experience of an application itself. Application developers should consider the security implications of their
design decisions, such as in user interfaces, and how they impact users.

Examples of things to avoid by default might include:

- Masking sensitive information in a user interface by default (for example on password/secret fields in a form or account page);
- Using more-secure options by default, rather than making them opt-in (for example by enabling two-factor authentication (2FA) by default for new users);
- Having a consistent user experience so that when users' muscle memory kicks in, they don't accidentally choose insecure options.

## Concrete Outcomes

From the workshops I definitely learned some new things, but it wasn't just a learning exercise, there were also some concrete actions
that came out of my experiences that I've already applied to Polly and other projects that I help maintain.

- Polly's licenses were audited, and now [allowed licenses are validated as part of CI][license-audit];
- Polly now [generates][generate-sbom] a [Software Bill of Materials][sbom] to aid with license and dependency auditing;
- Polly's build artifacts are now [attested][attestation] using [GitHub Attestations][github-attestations];
- Polly now has an [Incident Response Plan][irp];
- The Polly repository now allows any vulnerability to be [privately reported][security-policy];
- [Automatic Dependency Submission][automatic-dependency-submission] was enabled to allow for
  [Dependabot Automated Security Updates][dependabot-security-updates], such as [this one][security-update].

All the examples above are for Polly, but I've made similar changes to all of my personal GitHub repositories, as well as for
[Swashbuckle.AspNetCore][swashbuckle] and other repositories I maintain as part of my paid employment. I'm sure there's plenty of other
minor changes and tweaks I've made off the back of this experience over the last couple of months.

## Summary

It's been an honour to have been selected to participate in the GitHub Secure Open Source Fund, and I've really enjoyed the experience.
Whether I've learned new security related skills to apply to the open source ecosystem, or spoken to peers about their experiences, it's
been incredibly valuable to me both personally and professionally. I come away from the program with a wealth of new insights and transferable
skills that I can apply to software projects of any size and scope, both open and closed source.

Even if you think you've heard it all about security and open source software, it's hard to know what you don't know, so there's always
something new to learn, no matter how experienced you are with open source.

If you help maintain an open source project, I encourage you to apply for a future session of the GitHub Secure Open Source Fund. It's a
fantastic opportunity to enhance the security of your project and gain valuable insights and funding to help take your project forward.

Thanks again to GitHub and their funding partners for providing this program and helping to improve the security of Polly and the open
source community at scale. 💖

[aspire]: https://github.com/dotnet/aspire "The .NET Aspire project on GitHub"
[attestation]: https://github.com/App-vNext/Polly/pull/2647 "Attest artifacts"
[automatic-dependency-submission]: https://docs.github.com/code-security/supply-chain-security/understanding-your-software-supply-chain/configuring-automatic-dependency-submission-for-your-repository "Configuring automatic dependency submission for your repository"
[bootstrap]: https://getbootstrap.com/ "The Bootstrap website"
[codeql]: https://codeql.github.com/ "CodeQL"
[dependabot-security-updates]: https://docs.github.com/code-security/dependabot/dependabot-security-updates/about-dependabot-security-updates "About Dependabot security updates"
[dependency-review-action]: https://github.com/actions/dependency-review-action "GitHub's dependency-review-action"
[gregg-cochran]: https://github.com/dubsopenhub "Gregg Cochran's GitHub profile"
[expressjs]: https://expressjs.com/ "The Express.js website"
[fuzzing]: https://en.wikipedia.org/wiki/Fuzzing "Fuzzing on Wikipedia"
[github-attestations]: https://docs.github.com/actions/how-tos/secure-your-work/use-artifact-attestations/use-artifact-attestations "Using artifact attestations to establish provenance for builds"
[github-sosf]: https://resources.github.com/github-secure-open-source-fund/ "GitHub Secure Open Source Fund"
[github-sosf-annoucement]: https://github.blog/news-insights/company-news/announcing-github-secure-open-source-fund/ "Announcing GitHub Secure Open Source Fund: Help secure the open source ecosystem for everyone"
[github-sosf-session-2]: https://github.blog/open-source/maintainers/securing-the-supply-chain-at-scale-starting-with-71-important-open-source-projects/ "Securing the supply chain at scale: Starting with 71 important open source projects"
[generate-sbom]: https://github.com/App-vNext/Polly/pull/2640 "Generate SBOM"
[impostor-syndrome]: https://en.wikipedia.org/wiki/Impostor_syndrome "Impostor syndrome on Wikipedia"
[irp]: https://github.com/App-vNext/Polly/pull/2661 "Add incident response plan"
[junit]: https://junit.org/ "The JUnit website"
[kevin-crosby]: https://github.com/kevincrosby "Kevin Crosby's GitHub profile"
[license-audit]: https://github.com/App-vNext/Polly/pull/2641 "Specify allowed licenses"
[lotte-pitcher]: https://github.com/lottepitcher "Lotte Pitcher's GitHub profile"
[mvp]: http://martincostello.com/mvp "Martin Costello's MVP profile"
[mvp-summit-2025]: https://techcommunity.microsoft.com/blog/mvp-blog/a-recap-of-the-mvp-summit-2025/4403230 "A Recap of the MVP Summit 2025"
[osi-licences]: https://opensource.org/licenses "OSI Approved Licenses"
[polly]: https://github.com/App-vNext/Polly "The Polly project on GitHub"
[sbom]: https://en.wikipedia.org/wiki/Software_supply_chain "Software supply chain on Wikipedia"
[security-policy]: https://github.com/App-vNext/Polly/security/policy "Polly's security policy"
[security-update]: https://github.com/App-vNext/Polly/pull/2681 "Bump System.Text.Json from 8.0.0 to 9.0.7"
[swashbuckle]: https://github.com/domaindrivendev/Swashbuckle.AspNetCore "The Swashbuckle.AspNetCore project on GitHub"
