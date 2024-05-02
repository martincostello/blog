---
title: "Look ma, no Dockerfile! 🚫🐋 - Publishing containers with the .NET SDK 📦"
date: 2024-05-02
tags: containers,docker,dotnet
layout: bloglayout
description: "Publishing a .NET application as a container image using the .NET SDK without needing a Dockerfile and attesting the provenance of the image with GitHub Actions."
image: "https://cdn.martincostello.com/blog_dotnet-containers.jpg"
---

<img class="img-fluid mx-auto d-block" src="https://cdn.martincostello.com/blog_dotnet-containers.jpg" alt="A container ship being loaded with the .NET logo on the stern" title="A container ship being loaded with the .NET logo on the stern" height="384px" width="757px">

Containers have been a thing in the software ecosystem for a few years now, with lots of associated technologies and
concepts - Docker, Kubernetes, Helm charts, sidecars and many more. Using containers simplifies the deployment of your
application by reducing things down to a single artifact that you can deploy along with all the required dependencies,
including the operating system, and everything should Just Work™️. It's almost like shipping your whole machine off to production!

The cost for that simplicity of deployment is the steeper learning curve for you the developer to understand all these
additional concepts and technologies. In addition, you will likely need to revisit how you build your application, creating
a [`Dockerfile`][dockerfile] to produce a container image that contains everything you need to build and run your application.

The more complicated your build process, the more daunting this becomes - for example, if your application builds client-side
assets with a JavaScript toolchain. In that case, you need to install Node.js, npm etc. in the container build image too so you
can produce those assets. Things can quickly get complicated, and that's before you even start thinking about things like layer
caching, exposing ports, what user to run as and more. 😮‍💨

What if we could simplify a lot of that complexity and just build our application like we would if we weren't using containers, but
then just turn it into a container image? Well, the .NET 8 SDK [allows us to do exactly this][streamline-container-builds], meaning
you can containerise your application and not need a Dockerfile at all! 🚫🐋

Plus, as a bonus, if building with GitHub Actions, we can leverage this support to
[attest the provenance of our container images][using-artifact-attestations] with minimal additional effort. 🕵️🪪

READMORE

## Publishing your app as a container

As of the .NET 8 SDK release, you can now publish your application directly as a container image without needing to use a
Dockerfile at all. Container images are ultimately just a tarball, made up of layers, within which are a further series of
tarballs. Leveraging this, the .NET SDK can directly create the container image as a tarball file.

If you're already using the .NET SDK to publish your application for deployment, we only need to tweak a few parameters to
instead publish a container image.

Let's say that I now want to publish my application as a Linux x64 container image instead. I can do that like this:

```
dotnet publish ./src/MyProject --arch x64 --os linux -p:PublishProfile=DefaultContainer
```

By default this will create us an appropriate container image for our application using an appropriate base image
derived from our project and publish it to the local container registry. To publish it to a remote registry, we just
need to ensure that the appropriate credentials are available to publish to the registry and that we configure the
name of the registry.

I use GitHub Actions to build and publish my applications, so I can use the [docker/login-action][docker-login] to
authenticate with my container registry and then publish the container image to it. Here's a simplified example of
the additions to my build and deploy workflow needed to create and publish my application as a container image:

```
- name: Docker log in
  uses: docker/login-action@v3
  with:
    registry: ${{ env.CONTAINER_REGISTRY }}
    username: ${{ secrets.CONTAINER_REGISTRY_USERNAME }}
    password: ${{ secrets.CONTAINER_REGISTRY_PASSWORD }}

- name: Publish container
  id: publish-container
  shell: pwsh
  env:
    ContainerRegistry: ${{ env.CONTAINER_REGISTRY }}
    ContainerRepository: ${{ github.repository }}
  run: |
    dotnet publish ./src/API --arch x64 --os linux -p:PublishProfile=DefaultContainer

    # Make the container image name available to use later in the workflow
    $containerImage = "${env:ContainerRegistry}/${env:ContainerRepository}".ToLowerInvariant()
    "container-image=${containerImage}" >> ${env:GITHUB_OUTPUT}
```

In the first step the `docker/login-action` is used to authenticate with the container registry using the credentials.
Then `dotnet publish` is invoked to publish the project as a container image. Specifying `-p:PublishProfile=DefaultContainer`
tells the .NET SDK to publish the application as a container image, rather than publishing it as a standalone application.

The container publishing support comes with lots of sensible defaults built-in and you can customise the image that is
produced where it makes sense for you. The default smarts built-in include using [chiselled images][secure-container-builds]
if you publish a self-contained application, or [setting OCI labels][container-labels] on the image for you. With [SourceLink][sourcelink]
being built into the .NET SDK from .NET 8, this gives you lots of useful metadata about the image out-of-the-box.

As I use GitHub Actions to build my images, I can also leverage the [default environment variables][github-actions-environment-variables]
to automatically set up a lot of the configuration for me by convention. For example, when running in GitHub Actions I can
set various labels to associate the image with the repository and the source code used to build it:

```
<PropertyGroup Condition=" '$(CI)' == 'true' ">
  <ContainerImageTags>github-$(GITHUB_RUN_NUMBER)</ContainerImageTags>
  <ContainerImageTags Condition=" '$(GITHUB_HEAD_REF)' == '' ">$(ContainerImageTags);latest</ContainerImageTags>
  <ContainerRepository>$(GITHUB_REPOSITORY)</ContainerRepository>
  <ContainerTitle>$(GITHUB_REPOSITORY)</ContainerTitle>
  <ContainerVendor>$(GITHUB_REPOSITORY_OWNER)</ContainerVendor>
  <ContainerVersion>$(GITHUB_SHA)</ContainerVersion>
</PropertyGroup>
<ItemGroup Condition=" '$(CI)' == 'true' ">
  <ContainerLabel Include="com.docker.extension.changelog" Value="$(GITHUB_SERVER_URL)/$(GITHUB_REPOSITORY)/commit/$(GITHUB_SHA)" />
  <ContainerLabel Include="com.docker.extension.publisher-url" Value="$(GITHUB_SERVER_URL)/$(GITHUB_REPOSITORY_OWNER)" />
</ItemGroup>
```

In the above example I can automatically tag and name the image based on the GitHub Actions workflow used to publish it,
as well as include links to the release notes and publisher.

For my application where I tried this out, I also explicitly set `ContainerBaseImage` property to use the
[brand new Ubuntu 24.04][ubuntu-2404] base image for my container. This is currently only available in the .NET nightly container
images, which is why I need to set the full image name explicitly for the base image.

```
<ContainerBaseImage>mcr.microsoft.com/dotnet/nightly/runtime-deps:8.0-noble-chiseled-extra</ContainerBaseImage>
```

Once Ubuntu 24.04 images are available in the stable feed, I'll be able to simplfy this to just use `ContainerFamily` instead:

```
<ContainerFamily>noble-chiseled-extra</ContainerFamily>
```

As you can see, with some simple conventions, we can easily extend our build and publish process to switch to container images.

In fact, I did this with all my deployed applications over the last weekend. All of them have moved from a Windows App Service
application hosted in IIS to instead use a Linux App Service instance with container images. So long `Web.config` 👋 and not
a single Dockerfile in sight.

### One Gotcha

I did however hit one issue that's worth pointing out. If you publish a web application that contains files in a directory
that starts in a dot (e.g. `.well-known` for files like Apple site associations), the directory and its contents will not be
included in the published container image.

This is a [bug][well-known-issue] in the .NET SDK that will hopefully be fixed in a future release soon.

If you need to work around this issue before it's fixed, you can do [something like this][workaround] by renaming the directory
to not be prefixed with a `.` and then add an endpoint to manually serve the files from the folder to requests for those files.

```
app.MapGet(".well-known/{fileName}", (string fileName, IWebHostEnvironment environment) =>
{
    var file = environment.WebRootFileProvider.GetFileInfo(Path.Combine("well-known", fileName));
    if (file.Exists && file.PhysicalPath is { Length: > 0 })
    {
        return Results.File(file.PhysicalPath);
    }
    return Results.NotFound();
});
```

## Bonus: Attesting the provenance of your container

Attestation is the process of verifying a fact about something. In this context, we can _attest_ that the container image we
published to our artifact repository came from a known source. You can read more about attestation in this GitHub blog
post: [_Where does your software (really) come from?_][github-attestation].

Adding in an attestation that others (or ourselves) can use to verify our container image is really simple and builds on top
of the additional hardening me made to our container image at runtime as part of our publishing process.

First we add a small [MSBuild target][msbuild-target] that runs after the container image is published (the `PublishContainer` target).

In the target we get the digest of the container image that we published (`$(GeneratedContainerDigest)`) and write it the `GITHUB_OUTPUT`
file as an [output from our publish step][github-actions-set-output] named `container-digest`. This output can then be referenced
later in our workflow to attest the container image.

```
<Target Name="OutputContainerDigest" AfterTargets="PublishContainer" Condition=" '$(GITHUB_OUTPUT)' != '' ">
  <WriteLinesToFile File="$(GITHUB_OUTPUT)" Lines="container-digest=$(GeneratedContainerDigest)" />
</Target>
```

Now we can add a step to our GitHub Actions publishing workflow that consumes this value to attest our image using the
[`attest-build-provenance`][attest-build-provenance] action.

```
- name: Attest container image
  uses: actions/attest-build-provenance@v1
  with:
    push-to-registry: true
    subject-digest: ${{ steps.publish-container.outputs.container-digest }}
    subject-name: ${{ steps.publish-container.outputs.container-image }}
```

This action generates an attestation for the container image for the digest we published, and then pushes it to our
container registry and associates it with the manifest of that container image.

To ensure that the correct permissions are available to the GitHub Actions workflow to do this, ensure that the following
permissions are available to the job that runs the attestation step:

```
permissions:
  attestations: write
  contents: read
  id-token: write
```

Once our image is published and attested in the GitHub Actions workflow, we can then use [the GitHub CLI][github-cli] to verify the attestation.
You'll need at least version 2.49 of the GitHub CLI to do this.

In the case of [this build][build-and-deploy], I published the following container image to my registry: `martincostello/api:github-3279`

Now, if I ensure I'm logged in to my Azure Container Registry, I can verify the attestation for the image:

```
❯ gh attestation verify oci://martincostello.azurecr.io/martincostello/api:github-3279 -R martincostello/api
Loaded digest sha256:0053eef24e8a8dda09ceaae8acbdaeba85f4807c9ef13f8c026dfecaa5c4018f for oci://martincostello.azurecr.io/martincostello/api:github-3279
Loaded 2 attestations from GitHub API
✓ Verification succeeded!

sha256:0053eef24e8a8dda09ceaae8acbdaeba85f4807c9ef13f8c026dfecaa5c4018f was attested by:
REPO                PREDICATE_TYPE                  WORKFLOW
martincostello/api  https://spdx.dev/Document/v2.3  .github/workflows/build.yml@refs/heads/main
martincostello/api  https://slsa.dev/provenance/v1  .github/workflows/build.yml@refs/heads/main
```

Here we can see that the image was attested by the workflow that built it, and the provenance attestation was verified.
This gives us the confidence that if we want to run the image produced by this build, we can determine where it was published
from, and by extension what process, dependencies and [Git commit][attestation-commit] went into doing so.

## Summary

With the [support for publishing containers][streamline-container-builds] in the .NET 8 SDK we can easily create a container
image to deploy our application without having to substatially change our build process or even add a Dockerfile. We can then
[attest the provenance][using-artifact-attestations] of the image to enhance our supply chain security by establishing a whole
[_farm to table_][farm-to-table] view of the software we digest. 🥚🧑‍🍳🍳🍽️😋

I hope this blog post helps you to both simplify your .NET container build processes and enhance the security of your software supply chain. 📦🛡️

[attest-build-provenance]: https://github.com/actions/attest-build-provenance "attest-build-provenance action"
[attestation-commit]: https://github.com/martincostello/api/commit/830066f968dca5fd027f833b7f855bca3b812473 "Attestation and SBOMs"
[build-and-deploy]: https://github.com/martincostello/api/actions/runs/8920602364 "martincostello/api deployment #3279"
[container-labels]: https://github.com/dotnet/sdk/blob/96a1a0cfd87dd492e3ad340d7353437317ef667e/src/Containers/packaging/build/Microsoft.NET.Build.Containers.targets#L157-L173 "Default .NET SDK container labels"
[dockerfile]: https://docs.docker.com/reference/dockerfile/ "Dockerfile reference"
[docker-login]: https://github.com/docker/login-action "Docker login action"
[farm-to-table]: https://en.wikipedia.org/wiki/Farm-to-table "Farm-to-table"
[github-attestation]: https://github.blog/2024-04-30-where-does-your-software-really-come-from/ "Where does your software (really) come from?"
[github-actions-environment-variables]: https://docs.github.com/en/actions/learn-github-actions/variables#default-environment-variables "Default environment variables"
[github-actions-set-output]: https://docs.github.com/en/actions/using-workflows/workflow-commands-for-github-actions#setting-an-output-parameter "Setting an output parameter"
[github-cli]: https://cli.github.com/ "GitHub CLI"
[msbuild-target]: https://learn.microsoft.com/visualstudio/msbuild/msbuild-targets "MSBuild targets"
[secure-container-builds]: https://devblogs.microsoft.com/dotnet/secure-your-container-build-and-publish-with-dotnet-8/ "Secure your container build and publish with .NET 8"
[sourcelink]: https://learn.microsoft.com/en-us/dotnet/core/compatibility/sdk/8.0/source-link "Source Link included in the .NET SDK"
[streamline-container-builds]: https://devblogs.microsoft.com/dotnet/streamline-container-build-dotnet-8/ "Streamline your container build and publish with .NET 8"
[ubuntu-2404]: https://devblogs.microsoft.com/dotnet/whats-new-for-dotnet-in-ubuntu-2404/ "What’s new for .NET in Ubuntu 24.04"
[using-artifact-attestations]: https://docs.github.com/actions/security-guides/using-artifact-attestations-to-establish-provenance-for-builds "Using artifact attestations to establish provenance for builds"
[well-known-issue]: https://github.com/dotnet/sdk/issues/40511 "Directories with a dot as the first character of the directory name not included in published container images"
[workaround]: https://github.com/martincostello/website/commit/1d0ac0d96dc098b32c7b5b4805a787bb9cb93d77 "Fix missing .well-known files"
