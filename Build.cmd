@echo off

git rev-parse HEAD > version.txt
bundle exec middleman build %*
