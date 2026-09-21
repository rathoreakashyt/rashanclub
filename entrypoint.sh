#!/bin/sh
# Start cron (Laravel scheduler) + php-fpm
cron
exec php-fpm
