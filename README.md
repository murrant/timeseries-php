# timeseries-php

A lightweight, efficient PHP library for working with time series data.

WIP everything is subject to change


## Development
In the root directory run `composer install` (for IDE completion).

Go to `packages/web` and run `composer install`

Then run `composer dev` to start development services.

This will start a schedule job to collect network stats from your local ports so there is data to work with.

Visit `http://localhost:8000` and view an example graph.

Monorepo notes:
  - Packages are located in the `packages` directory.
  - Each package has its own `composer.json` for dependency management.
  - `composer merge` in the root merges dependencies across all packages.
