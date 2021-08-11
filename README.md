# PhpStorm + Xdebug with PHP-PM
 - Run `docker-compose up -d`
 - After your containers are up & running execute `./composer-install.sh`
 - Attach to `xdebug_api` container and run `composer behatx features/heartcheck.feature`