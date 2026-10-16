#!/usr/bin/env bash
set -e

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$DIR"

if ! command -v php &>/dev/null; then
    echo "PHP not found. Install PHP >=8.2 and required extensions."
    exit 1
fi

if ! command -v composer &>/dev/null; then
    echo "Composer not found."
    exit 1
fi

if [ ! -d "vendor" ]; then
    echo "Installing PHP dependencies..."
    composer install
fi

echo "Starting Senat Polines API on http://127.0.0.1:8000 ..."
php artisan serve
