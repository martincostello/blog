#! /usr/bin/env pwsh

#Requires -PSEdition Core
#Requires -Version 7

git rev-parse HEAD > version.txt
git rev-parse --abbrev-ref HEAD > branch.txt

bundler exec middleman build

if ($LASTEXITCODE -ne 0) {
    Write-Host "middleman build failed with exit code $LASTEXITCODE"
}
