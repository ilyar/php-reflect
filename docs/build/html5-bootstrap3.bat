@ECHO OFF

REM ---
REM --- Windows CMD script
REM --- to build PHP Reflect documentation in HTML 5 / Bootstrap 3 format
REM ---
REM --- Released under the Apache 2 license (http://www.apache.org/licenses/LICENSE-2.0.html)
REM --- (c) 2014 Laurent Laville
REM ---


IF "%ASCIIDOC%"==""       SET "ASCIIDOC=C:\asciidoc-8.6.9"
IF "%ASCIIDOC_BIN%"==""   SET "ASCIIDOC_BIN=%ASCIIDOC%\asciidoc.py"
IF "%ASCIIDOC_THEME%"=="" SET "ASCIIDOC_THEME=cerulean"

REM --
REM -- WEB HTML5 BOOTSTRAP FORMAT
REM --
ECHO GENERATING WEB HTML5 BOOTSTRAP FORMAT ...

REM --
REM -- USER GUIDE
REM --
ECHO BUILDING USER GUIDE ...

FOR %%f IN (user-guide*.asciidoc) DO (
"%ASCIIDOC_BIN%" -b bootstrap -a linkcss -a navbar=fixed -a totop -a theme=%ASCIIDOC_THEME% %%f
)

REM --
REM -- DEVELOPER GUIDE
REM --
ECHO BUILDING DEVELOPER GUIDE ...

FOR %%f IN (developer-guide*.asciidoc) DO (
"%ASCIIDOC_BIN%" -b bootstrap -a linkcss -a navbar=fixed -a totop -a theme=%ASCIIDOC_THEME% %%f
)
"%ASCIIDOC_BIN%" -b bootstrap -a linkcss -a navbar=fixed -a totop -a theme=%ASCIIDOC_THEME% api-compared.asciidoc

REM --
REM -- MIGRATION GUIDE
REM --
ECHO BUILDING MIGRATION GUIDE ...

"%ASCIIDOC_BIN%" -b bootstrap -a linkcss -a navbar=fixed -a totop -a theme=%ASCIIDOC_THEME% migration-guide.asciidoc

REM --
REM -- GETTING STARTED page
REM --
ECHO BUILDING GETTING STARTED ...

"%ASCIIDOC_BIN%" -b bootstrap -a linkcss -a navbar=fixed -a totop -a theme=%ASCIIDOC_THEME% getting-started.asciidoc

REM --
REM -- MAN page
REM --
ECHO BUILDING MAN PAGE ...

"%ASCIIDOC_BIN%" -b bootstrap -a linkcss -a navbar=fixed -a totop -a theme=%ASCIIDOC_THEME% -d article phpreflect.1.asciidoc
