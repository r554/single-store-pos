@echo off
title POS Print Agent
cd /d "%~dp0\.."
node print-agent/agent.mjs
pause
