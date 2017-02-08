@echo off

SET PATH=%PATH%;D:\home\site\deployments\tools\r\ruby-2.2.2-x64-mingw32\bin

git rev-parse HEAD > version.txt
git rev-parse --abbrev-ref HEAD > branch.txt

bundle exec middleman build %*
