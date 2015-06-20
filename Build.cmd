@echo off

git rev-parse > version.txt
bundle exec middleman build %*
