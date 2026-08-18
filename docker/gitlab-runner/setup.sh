#!/bin/bash
# Скрипт установки GitLab Runner на сервере деплоя
# Запускать на СЕРВЕРЕ (staging/prod), не локально
#
# Использование:
#   chmod +x setup.sh
#   sudo ./setup.sh

set -e

GITLAB_URL="$1"       # например: https://gitlab.mycompany.com
RUNNER_TOKEN="$2"     # токен из GitLab → Settings → CI/CD → Runners → New runner

if [ -z "$GITLAB_URL" ] || [ -z "$RUNNER_TOKEN" ]; then
    echo "Использование: sudo ./setup.sh <GITLAB_URL> <RUNNER_TOKEN>"
    echo "Пример: sudo ./setup.sh https://gitlab.example.com glrt-xxxxxxxxxxxx"
    exit 1
fi

echo "==> Установка GitLab Runner..."
curl -L "https://packages.gitlab.com/install/repositories/runner/gitlab-runner/script.deb.sh" | sudo bash
sudo apt-get install -y gitlab-runner

echo "==> Регистрация runner..."
sudo gitlab-runner register \
    --non-interactive \
    --url "$GITLAB_URL" \
    --token "$RUNNER_TOKEN" \
    --executor "docker" \
    --docker-image "alpine:latest" \
    --description "staging-runner" \
    --docker-volumes "/var/run/docker.sock:/var/run/docker.sock"

echo "==> Запуск runner..."
sudo systemctl enable gitlab-runner
sudo systemctl start gitlab-runner

echo ""
echo "==> Runner установлен и запущен!"
echo "    Проверка: sudo gitlab-runner status"
