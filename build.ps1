#! /usr/bin/env pwsh

#Requires -PSEdition Core
#Requires -Version 7

param(
    [Parameter(Mandatory = $false)][switch] $Serve
)

if (-Not ${env:GIT_COMMIT_SHA}) {
    ${env:GIT_COMMIT_SHA} = ${env:GITHUB_SHA} ?? (git rev-parse HEAD)
}
if (-Not ${env:GIT_BRANCH}) {
    ${env:GIT_BRANCH} = ${env:GITHUB_REF_NAME} ?? (git rev-parse --abbrev-ref HEAD)
}

if ($Serve) {
    ${env:HUGO_PARAMS_analyticsId} = ""
    ${env:HUGO_PARAMS_renderAnalytics} = "false"
    hugo server --buildDrafts --buildFuture
}
else {
    npm run all
}

if ($LASTEXITCODE -ne 0) {
    throw "build failed with exit code $LASTEXITCODE"
}
