@ECHO OFF

rem must be on the env already
if "%PHP_SDK_ROOT_PATH%"=="" (
	echo PHP SDK is not setup
	exit /b 3
)

set yyyy=%date:~-4%
set mm=%date:~4,2%
set dd=%date:~0,2%

set hh=%time:~0,2%
if %hh% lss 10 (set hh=0%time:~1,1%)
set nn=%time:~3,2%
set ss=%time:~6,2%
set cur_date=%yyyy%%mm%%dd%-%hh%%nn%%ss%

call %~dp0rmtools_setvars.bat 

set LOG_FILE=%PHP_RMTOOLS_LOG_PATH%\task-pecl-%cur_date%.log
set LOCK_FILE=%PHP_RMTOOLS_LOCK_PATH%\pecl.lock

if "%1"=="" goto :help
if "%1"=="--help" goto :help
if "%1"=="-h" goto :help
if "%1"=="/?" goto :help
goto :skip_help

:help
echo ==========================================================
echo This is the PECL build batch script. You can see the help
echo output of the underlaying worker below. Note that you HAVE
echo TO ommit the --config option when running this batch.
echo ==========================================================
call "%PHP_SDK_ROOT_PATH%\phpsdk-starter.bat" -c vc14 -a x64 -t "%PHP_RMTOOLS_BIN_PATH%\pecl.bat"
echo .
exit /b 0

:skip_help

IF EXIST "%LOCK_FILE%" (
	ECHO Pecl build script is already running.
	echo .
	exit /b 3
)

ECHO running > "%LOCK_FILE%"

rem Notice the --first and the --last calls marked, that's important
rem to maintain the state between call for the same package. For instance
rem if --aggregate-mail is used.
call "%PHP_SDK_ROOT_PATH%\phpsdk-starter.bat" -c vc14 -a x64 -t "%PHP_RMTOOLS_BIN_PATH%\pecl.bat" --task-args "--config=pecl70_x64 --first %*" >> "%LOG_FILE%" 2<&1
call "%PHP_SDK_ROOT_PATH%\phpsdk-starter.bat" -c vc14 -a x64 -t "%PHP_RMTOOLS_BIN_PATH%\pecl.bat" --task-args "--config=pecl70_x86 %*" >> "%LOG_FILE%" 2<&1
call "%PHP_SDK_ROOT_PATH%\phpsdk-starter.bat" -c vc14 -a x64 -t "%PHP_RMTOOLS_BIN_PATH%\pecl.bat" --task-args "--config=pecl56_x64 %*" >> "%LOG_FILE%" 2<&1
call "%PHP_SDK_ROOT_PATH%\phpsdk-starter.bat" -c vc14 -a x64 -t "%PHP_RMTOOLS_BIN_PATH%\pecl.bat" --task-args "--config=pecl56_x86 %*" >> "%LOG_FILE%" 2<&1
call "%PHP_SDK_ROOT_PATH%\phpsdk-starter.bat" -c vc14 -a x64 -t "%PHP_RMTOOLS_BIN_PATH%\pecl.bat" --task-args "--config=pecl55_x64 %*" >> "%LOG_FILE%" 2<&1
call "%PHP_SDK_ROOT_PATH%\phpsdk-starter.bat" -c vc14 -a x64 -t "%PHP_RMTOOLS_BIN_PATH%\pecl.bat" --task-args "--config=pecl55_x86 %*" >> "%LOG_FILE%" 2<&1
call "%PHP_SDK_ROOT_PATH%\phpsdk-starter.bat" -c vc14 -a x64 -t "%PHP_RMTOOLS_BIN_PATH%\pecl.bat" --task-args "--config=pecl54 %*" >> "%LOG_FILE%" 2<&1
call "%PHP_SDK_ROOT_PATH%\phpsdk-starter.bat" -c vc14 -a x64 -t "%PHP_RMTOOLS_BIN_PATH%\pecl.bat" --task-args "--config=pecl53 --last %*" >> "%LOG_FILE%" 2<&1

echo Done.>> "%LOG_FILE%"

del "%LOCK_FILE%" >> "%LOG_FILE%" 2<&1

echo .
exit /b 0

