#!/bin/sh
# Docker entrypoint: cron (Laravel scheduler) + php-fpm dono start karo

# Cron ko root se start karo (background mein)
cron

# php-fpm foreground mein (main process)
exec php-fpm
