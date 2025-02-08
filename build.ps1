#! /usr/bin/env pwsh

#Requires -PSEdition Core
#Requires -Version 7

param(
    [Parameter(Mandatory = $false)][switch] $Serve
)

git rev-parse HEAD > version.txt
git rev-parse --abbrev-ref HEAD > branch.txt

bundler exec middleman build

if ($LASTEXITCODE -ne 0) {
    throw "middleman build failed with exit code $LASTEXITCODE"
}

if ($Serve) {
    bundler exec middleman serve
}
