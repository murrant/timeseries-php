# timeseries-php

A lightweight, efficient PHP library for working with time series data.

WIP everything is subject to change


## Development

0. Dependencies (Debian package names)
 - composer
 - npm
 - docker.io
 - docker-compose
 - vite
 - php-sqlite
 - php-xml
 - php-rrd (for native RRD updates)
 - rrdtool (for CLI RRD updates)
1. In the root directory run `composer install` (for IDE completion).
2. Go to `packages/web` and run `composer install`
3. Start docker tsdb containers `cd tests/docker` and `docker compose up -d`
4. Then go back to `packages/web` and run:
   1. `cp .env.example .env`
   1. `./artisan key:generate`
   1. `./artisan migrate`
5. To run the web interface for testing, go to `packages/web` and run:
  - `composer dev` to start development services
  
   OR

  - `QT_QPA_PLATFORM=offscreen composer dev` if you don't have a X11 display available

This will also run a schedule job to collect network stats from your local ports so there is data to work with.

Visit `http://localhost:8000` and view an example graph.

6. To allow external access, install nginx and replace the default site configuration with the following:
```
server {
        listen 80 default_server;
        listen [::]:80 default_server;

        server_name _;

        location / {
                proxy_pass http://127.0.0.1:8000;
                proxy_set_header Host $http_host;
        }
}
```

Monorepo notes:
  - Packages are located in the `packages` directory.
  - Each package has its own `composer.json` for dependency management.
  - `composer merge` in the root merges dependencies across all packages.
