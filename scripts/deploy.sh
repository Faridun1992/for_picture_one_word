#!/bin/bash
# Скрипт деплоя — запускается на сервере через SSH из CI/CD
set -e

DEPLOY_PATH="${DEPLOY_PATH:-/var/www/project}"
DEPLOY_BRANCH="${DEPLOY_BRANCH:-dev}"

cd "$DEPLOY_PATH"

flock /tmp/project-laravel-schedule.lock make deploy-stage DEPLOY_BRANCH="$DEPLOY_BRANCH"
