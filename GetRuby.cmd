REM Based on, and thanks to, https://github.com/shanselman/march-is-for-makers/blob/master/getruby.cmd

@echo ON

REM Put Ruby in Path
REM You can also use %TEMP% but it is cleared on site restart. Tools is persistent.
SET PATH=%PATH%;D:\home\site\deployments\tools\r\ruby-2.2.2-x64-mingw32\bin

SET _7ZIP="%PROGRAMFILES%\7-Zip\7z.exe"
if not exist %_7ZIP% SET _7ZIP=d:\7zip\7za

REM I am in the repository folder
pushd D:\home\site\deployments\tools 
if not exist r md r
cd r 
if exist ruby-2.2.2-x64-mingw32 goto end

echo No Ruby, need to get it!

REM Get Ruby and Rails
curl -o ruby222.zip -L http://dl.bintray.com/oneclick/rubyinstaller/ruby-2.2.2-x64-mingw32.7z?direct
REM Azure puts 7zip here!
echo START Unzipping Ruby
SetLocal DisableDelayedExpansion & %_7ZIP% x -xr!*.ri -y ruby222.zip > rubyout
echo DONE Unzipping Ruby

REM Get DevKit to build Ruby native gems  
REM If you don't need DevKit, rem this out.
curl -o DevKit.zip http://cdn.rubyinstaller.org/archives/devkits/DevKit-mingw64-64-4.7.2-20130224-1432-sfx.exe
echo START Unzipping DevKit
%_7ZIP% x -y -oDevKit DevKit.zip > devkitout
echo DONE Unzipping DevKit

REM Init DevKit
ruby DevKit\dk.rb init

REM Tell DevKit where Ruby is
echo --- > config.yml
echo - D:/home/site/deployments/tools/r/ruby-2.2.2-x64-mingw32 >> config.yml

REM Setup DevKit
ruby DevKit\dk.rb install

popd

:end

REM Need to be in Repository
cd %DEPLOYMENT_SOURCE%
cd

call gem install bundler

echo Bundler install (not update!)
call bundle install

cd %DEPLOYMENT_SOURCE%
cd
