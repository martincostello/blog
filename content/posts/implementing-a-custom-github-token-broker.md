---
title: Implementing a custom GitHub token broker
date: 2026-06-16
tags: dotnet,typescript,github,actions,jwt,oidc
layout: post
description: "Implementing a custom GitHub token broker to exchange GitHub Actions OIDC tokens for GitHub access tokens using C# and TypeScript."
cdnImage: "" # TODO
---

With the recent surge in [supply chain attacks][shai-hulud] targeting open source projects, I wanted to improve
the security of my GitHub Actions workflows, and by extension my GitHub account, by implementing a custom GitHub
token broker to exchange GitHub Actions OIDC tokens for GitHub access tokens.

In this blog post I'll cover the background motivation for why I decided to implement a token broker, how it
works, and how you can implement your own GitHub token broker based on a [sample GitHub repository][sample-repo]
I've put together.

<!--more-->

## Background

Recently there has been a surge in supply chain attacks targeting open source projects. Projects are indirectly
targeted through their dependencies, with malicious code published to package managers such as npm. The threat
actor's aim is to achieve code execution through installation on either developers' local machines or through CI
workflows in systems such as GitHub Actions itself.

Once execution is achieved, malicious code executes with the goal of exfiltrating secrets, such as AWS and
GitHub access tokens, to remote command-and-control (C2) servers to allow the attacker to further compromise a
project and its users.

Notable recent examples of these supply chain attacks have included [CVE-2026-33634][trivy-compromise], which
targeted [Trivy][trivy], and [CVE-2026-45321][tan-stack-compromise], which targeted packages published by `@tanstack`
in npm. Both of these attacks were achieved through the publication of malicious code to Docker Hub, GitHub Actions
and/or npm, which then spread to their users through CI/CD systems via dependency updates using tools like
[dependabot][dependabot] and [renovate][renovate].

I like to think of myself as fairly security-conscious, but I was caught out by the Trivy compromise and merged
a pull request containing one of the compromised versions of Trivy. Luckily, I only run Trivy overnight on a
schedule, and the compromised code never actually ran in any of my GitHub Actions workflows. I was lucky.

After the TanStack compromise, I started to think about how I could improve the security of my GitHub Actions
workflows so that if malicious code were to execute in one of my GitHub repositories in the future I could
minimise the blast radius of any damage if a GitHub secret was exfiltrated by a malicious third party.

For a long time, I've relied on [GitHub Actions secrets][actions-secrets] to store secrets such as GitHub access
tokens to use in my GitHub Actions workflows. The [`GITHUB_TOKEN`][github-token] built-in to GitHub Actions is
the go-to solution for authenticating with the GitHub API in many scenarios, but it has some limitations. The
primary limitation is that it is only valid in the scope of the repository it is executing in - it also has
restrictions regarding the triggering of other workflows, which can make release orchestration difficult to manage.

To work around these restrictions, I've used [GitHub apps][github-apps] and [personal access tokens][github-pats]
in the past, but to use these in GitHub Actions you need to store their secrets somewhere, which I used to do
by using GitHub Actions secrets. However, if a malicious actor is able to execute in one of your GitHub Actions
workflows and extract these secrets, they would be able to pivot to other repositories and potentially compromise
significant portions of your user account and/or GitHub organisation.

Tools such as [Zizmor][zizmor] can help to mitigate this risk by scanning your GitHub Actions workflows and
highlighting code patterns that can be refactored or removed to minimise risk, and [npm v12][npm-v12] will
disable `npm install` scripts by default, but ultimately the secrets are still present in the workflows.

To mitigate this risk even further, I decided that I wanted to implement a custom GitHub token broker that would
allow me to exchange short-lived secrets in my GitHub Actions workflows for a GitHub access token that that has
only the permissions required for the task at hand and completely remove the GitHub Actions secrets.

To do this, I wanted to leverage GitHub Actions OIDC to build a broker to acquire secrets dynamically at runtime.

## What is GitHub Actions OIDC?

[GitHub Actions OpenID Connect (OIDC)][actions-oidc] is a feature of GitHub Actions that allows you to authenticate
with cloud providers and other services without needing to store long-lived secrets in your GitHub Actions workflows.

When the `id-token: write` permission is available to a workflow job, you can [acquire an OIDC token][get-oidc-token]
from the GitHub Actions runtime via the `ACTIONS_ID_TOKEN_REQUEST_URL` and `ACTIONS_ID_TOKEN_REQUEST_TOKEN` environment
variables. Making an HTTP request using these variables will return a JSON Web Token (JWT) that can be used to authenticate
with other services that support OIDC, such as [AWS][aws-oidc] and [Azure][azure-oidc].

The JWT is cryptographically signed by GitHub and contains claims that securely identify the workflow job, repository
and more that you can use to make authentication and authorisation decisions. For example the `repository_owner` claim
can be used to determine the owner of the GitHub repository that acquired the JWT and the `workflow` claim can be used
to determine which GitHub Actions workflow it originated from. [Many other claims are available][oidc-claims] that can
be used to make fine-grained authorisation decisions for how and when to allow custom logic for your automation. The
JWT is also short-lived and expires as soon as the GitHub Actions workflow completes.

GitHub Actions OIDC is supported by many well-known third-party services, but what if you want to use it in a custom
integration? As ultimately the JWT is a standard OIDC token, you can use it to authenticate in custom code the same as
any generic authentication solution, such as [JWT Bearer Authentication][bearer-auth].

## Implementing the GitHub token broker

So how does the GitHub token broker work?

At a high level, the GitHub token broker is an ASP.NET Core Minimal API endpoint secured with
[JWT Bearer Authentication][bearer-auth]. For my own usage, the broker is embedded within my [Costellobot][costellobot]
GitHub application to process GitHub webhooks and other automation-related tasks.

The OIDC token is verified as being issued by GitHub and within its validity window. It also checks that it has a
`repository_owner` claim equal to my GitHub login (`martincostello`). If these conditions are met the request is
authenticated as part of the standard ASP.NET Core authentication pipeline using the JWT bearer authentication provider.

Once the request is _authenticated_, the request needs to be _authorised_. The token broker is configured with a
dictionary of GitHub repository names, which are composed of a dictionary of what I've called a _"token profile"_.
These token profiles specify a GitHub app or a named GitHub personal access token (PAT) to use, and a set of policy
rules to determine which branches, events and workflows are allowed to acquire a GitHub access token in the context
of that profile.

Here's an example of a configured token profile named `benchmarks`:

```
"martincostello/costellobot": {
  "benchmarks": {
    "AppId": "3842668",
    "AppPermissions": {
      "contents": "write",
      "issues": "write"
    },
    "Branches": ["*"],
    "Events": [
      "push",
      "workflow_dispatch"
    ],
    "TargetRepositories": [
      "benchmarks",
      "costellobot"
    ],
    "Workflows": ["benchmark.yml"]
  }
}
```

This effectively says:

> The `benchmark` workflow in the `martincostello/costellobot` repository can use the GitHub app with the ID
> `3842668` to acquire a GitHub app installation access token with `contents: write` and `issues: write`
> permissions when the workflow is triggered from any branch from either a `push` or `workflow_dispatch` event
> with access to the `martincostello/costellobot` and `martincostello/benchmarks` repositories.

If the profile is found for the current repository and authorisation is successful, the token broker will do
one of two things, depending on whether the profile uses a GitHub app or a GitHub PAT. For a GitHub app,
it will use the configured permissions and target repositories to create a GitHub app installation access token.
For a GitHub PAT, it will make a request to an [Azure Key Vault][azure-key-vault] instance to retrieve the GitHub
PAT secret value. The endpoint will then return the access token to the caller in the JSON response body.

The profiles support the principle of least privilege, ensuring an access token can only be acquired for the exact
set of conditions where I intend the workflow to run and for what it needs to do.

By default, the generated GitHub app token will be scoped to the same repository that requested it. The profiles
also allow for specifying an allowlist of [GitHub Environments][github-environments], tags and custom JWT claims
to further restrict the conditions under which a token can be acquired.

Where possible, all the profiles use a GitHub app, but I added support for GitHub PATs for use cases where I need
to orchestrate requests across multiple GitHub organisations/users and an appropriate app is not installed in all
of these accounts.

I have intentionally decided not to allow the caller to specify the permissions and target repositories for the
access token themselves, as this would allow a malicious workflow to potentially expand the scope of the access
token beyond what was originally intended. If I need to change the permissions or target repositories for a profile,
I can do this by updating the token broker configuration in Costellobot and re-deploying it.

Tokens issued for a GitHub app installation are valid for a period of one hour (more on this later).

## Using the GitHub token broker

Now that there's a token broker endpoint that can exchange GitHub Actions OIDC tokens for GitHub access tokens,
how would we use it from a GitHub Actions workflow to get a token?

To make things as simple and idiomatic as possible, I implemented a [custom GitHub Action][custom-action] that can
be used in any workflow to acquire a GitHub access token from the token broker. The action is implemented in TypeScript
and can be used in any of my repositories' workflows. Below is an example of how to use the action in a workflow
to acquire a GitHub access token for the `benchmarks` profile described above:

```
- name: Get GitHub token
  id: get-github-token
  uses: martincostello/github-automation/actions/get-github-token@18237c275f9a773966e3e52dcabe5e7e559a9786 # get-github-token/v4.3.1
  with:
    profile-name: benchmarks
```

A subsequent workflow step can then use the token in the `token` output for its own needs. You can see a concrete
example of this in use [in this workflow][example-usage].

When the token returned by the action is a GitHub app installation access token, the action will automatically
revoke the token when the workflow completes, so that it cannot be used again. This is done by defining a `post` step
for the [custom action][custom-actions] that will call the GitHub API's [`DELETE /installation/token`][revoke-token]
endpoint when the workflow completes. This minimises the risk if a token is exfiltrated from the workflow by cutting
short the default one-hour lifetime of the token to the duration of the workflow run. In some cases, the tokens issued
to my GitHub Actions workflows might only be valid for less than a minute.

## Creating your own GitHub token broker

If you found this approach interesting and want to implement your own GitHub token broker, I've put together a
[sample repository][sample-repo] that contains a working implementation of a token broker and a custom GitHub Action
to acquire a GitHub access token from it.

The sample repository is implemented in C# and TypeScript as it's extracted from two other repositories containing
my own implementations for my personal projects, but the concepts can be applied to any programming language and
framework that supports OIDC and JWTs. The sample also contains tests for the token broker and the custom action
for both positive and negative scenarios, so you can use it as a reference to see how it works in more depth.

Note that I've archived the repository as I do not intend to maintain it on an ongoing basis, but you are welcome
to fork it and use it as a starting point for your own implementation.

If the approach I'm using evolves over time, you can always take a look at my [Costellobot][costellobot] repository
to see how I'm using it in my own GitHub Actions workflows for production-equivalent usage.

The sample implementation is provided for demonstration purposes only and is not intended to be used in production
as-is. Feel free to use it as a reference for your own implementation, but you should review the code and make any
necessary changes to ensure it meets your security and operational requirements.

## Conclusion

Before I implemented my GitHub token broker, I was storing GitHub access tokens in GitHub Actions secrets, with the
same broadly-scoped GitHub PAT being used across dozens of repositories. Now with the GitHub token broker, I've been
able to delete _every single GitHub PAT_ from my GitHub Actions secrets. Where possible, every scenario now uses a
GitHub app installation access token with the minimum permissions required scoped to only the repositories that need
it. For the few scenarios where I still need to use a GitHub PAT, I have generated new tokens with the minimum scopes
required and stored them in an [Azure Key Vault][azure-key-vault] instance. If any one token is compromised, I can
revoke it and generate a new one without it affecting other repositories.

I hope you've found this post interesting and that it has given you some ideas for how you can improve the security
of your own GitHub Actions workflows and software supply chain.

[actions-oidc]: https://docs.github.com/actions/concepts/security/openid-connect
[actions-secrets]: https://docs.github.com/actions/concepts/security/secrets
[aws-oidc]: https://docs.github.com/actions/how-tos/secure-your-work/security-harden-deployments/oidc-in-aws
[azure-oidc]: https://docs.github.com/actions/how-tos/secure-your-work/security-harden-deployments/oidc-in-azure
[azure-key-vault]: https://learn.microsoft.com/azure/key-vault/general/overview
[bearer-auth]: https://learn.microsoft.com/aspnet/core/security/authentication/configure-jwt-bearer-authentication
[costellobot]: https://github.com/martincostello/costellobot
[custom-action]: https://github.com/martincostello/github-automation/tree/main/actions/get-github-token
[custom-actions]: https://docs.github.com/actions/concepts/workflows-and-actions/custom-actions
[dependabot]: https://docs.github.com/code-security/tutorials/secure-your-dependencies/dependabot-quickstart
[example-usage]: https://github.com/martincostello/costellobot/blob/286793adc172887a2fc4d56aadba285c54609f91/.github/workflows/benchmark.yml#L71-L87
[get-oidc-token]: https://docs.github.com/actions/reference/security/oidc#methods-for-requesting-the-oidc-token
[github-environments]: https://docs.github.com/actions/how-tos/deploy/configure-and-manage-deployments/manage-environments
[github-apps]: https://docs.github.com/apps/creating-github-apps/about-creating-github-apps/about-creating-github-apps
[github-pats]: https://docs.github.com/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens
[renovate]: https://github.com/renovatebot/renovate
[github-token]: https://docs.github.com/actions/tutorials/authenticate-with-github_token
[npm-v12]: https://github.blog/changelog/2026-06-09-upcoming-breaking-changes-for-npm-v12/
[oidc-claims]: https://docs.github.com/actions/reference/security/oidc#oidc-token-claims
[revoke-token]: https://docs.github.com/rest/apps/installations#revoke-an-installation-access-token
[sample-repo]: https://github.com/martincostello/github-token-broker-sample
[shai-hulud]: https://www.ncsc.gov.uk/blogs/software-supply-chain-attacks-check-your-dependencies
[tan-stack-compromise]: https://github.com/TanStack/router/security/advisories/GHSA-g7cv-rxg3-hmpx
[trivy]: https://github.com/aquasecurity/trivy
[trivy-compromise]: https://github.com/aquasecurity/trivy/security/advisories/GHSA-69fq-xp46-6x23
[zizmor]: https://github.com/zizmorcore/zizmor
