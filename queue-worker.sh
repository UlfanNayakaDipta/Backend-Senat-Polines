#!/usr/bin/env bash
# Worker queue Backend-Senat-Polines (dipakai pm2: senat-queue)
cd /home/ubuntu/Backend-Senat-Polines || exit 1
exec php artisan queue:work --queue=default --tries=1 --timeout=3600 --sleep=1 --max-time=1800
