#! /usr/bin/env pwsh

#Requires -PSEdition Core
#Requires -Version 7

git rev-parse HEAD > version.txt
git rev-parse --abbrev-ref HEAD > branch.txt

bundle exec middleman build
