#! /usr/bin/env pwsh

#Requires -PSEdition Core
#Requires -Version 7

param(
    [Parameter(Mandatory = $false)][switch] $Serve
)

# Set environment variables for git info (if not already set)
if (-not $env:GIT_COMMIT_SHA) {
    $env:GIT_COMMIT_SHA = git rev-parse HEAD
}
if (-not $env:GIT_BRANCH) {
    $env:GIT_BRANCH = git rev-parse --abbrev-ref HEAD
}

hugo

if ($LASTEXITCODE -ne 0) {
    throw "hugo build failed with exit code $LASTEXITCODE"
}

if ($Serve) {
    hugo server --buildDrafts --buildFuture
}
