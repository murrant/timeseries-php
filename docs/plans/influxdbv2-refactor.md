# Plan: Extract InfluxDB v2 into a dedicated driver `InfluxDBv2`

Date: 2025-08-15
Owner: Core Drivers Working Group
Status: Proposed

## Goal

Refactor the current mixed InfluxDB driver (which supports both API v1 and v2) into two cleanly separated drivers by creating a new driver named `InfluxDBv2`. The objective is to reduce complexity, improve maintainability, and enforce single-responsibility boundaries. The existing `InfluxDB` driver will remain but will be reduced to v1-only behavior.

## Non‑Goals

- Implementing new features for InfluxDB v2 beyond what currently exists.
- Providing long-term BC between v1 and v2 within the same driver (BC is not required for the package under development).

## High‑Level Approach

- Create a new driver under `src/Drivers/InfluxDBv2` with its own configuration, query builder, schema manager, connection adapters, and commands.
- Remove v2-specific conditionals and abstractions from the existing `InfluxDB` driver; keep it v1-focused.
- Migrate tests to mirror the new driver and ensure parity of behavior.
- Update documentation and examples to reflect the split.

## Target Directory and Namespace Layout

```
src/Drivers/
├── InfluxDB/                 # (v1 only after refactor)
│   ├── Connection/
│   │   ├── HttpConnectionAdapter.php         # v1-only
│   │   ├── UdpConnectionAdapter.php          # v1-only (if used)
│   │   └── Command/
│   │       ├── InfluxDBHttpCommandFactory.php  # v1-only (remove v2 logic)
│   │       └── V1/ ...                        # keep only V1 commands
│   ├── InfluxDBConfig.php    # remove api_version; v1-only options
│   ├── InfluxDBDriver.php    # v1-only driver
│   ├── InfluxDBQueryBuilder.php (if present)
│   ├── InfluxDBRawQuery.php (InfluxQL)
│   └── Schema/
│       └── InfluxDBSchemaManager.php          # v1-only (InfluxQL)
│
└── InfluxDBv2/
    ├── Connection/
    │   └── HttpConnectionAdapter.php          # dedicated to v2 APIs
    │       # No UDP; v2 uses HTTP API
    │       └── Command/
    │           └── V2/QueryCommand.php
    │           └── V2/WriteCommand.php
    │           └── V2/HealthCommand.php
    │           └── V2/PingCommand.php
    │           └── V2/GetBucketsCommand.php
    │           └── V2/CreateBucketCommand.php
    │           └── V2/DeleteMeasurementCommand.php
    ├── InfluxDBv2Config.php
    ├── InfluxDBv2Driver.php
    ├── InfluxDBv2QueryBuilder.php (Flux)
    ├── InfluxDBv2RawQuery.php (Flux)
    └── Schema/
        └── InfluxDBv2SchemaManager.php        # Flux-based schema manager
```

PSR-4 namespace roots:
- `TimeSeriesPhp\Drivers\InfluxDB\...` (existing v1 only)
- `TimeSeriesPhp\Drivers\InfluxDBv2\...` (new)

Composer autoload rules (composer.json) already map `src/` as PSR-4 root; new directories will be discovered automatically.

## Classes and Responsibilities

1. InfluxDBv2Driver (extends AbstractTimeSeriesDB)
   - Uses `#[Driver(name: 'influxdbv2', queryBuilderClass: InfluxDBv2QueryBuilder::class, configClass: InfluxDBv2Config::class, schemaManagerClass: Schema\InfluxDBv2SchemaManager::class)]`.
   - Responsible for connect/write/writeBatch/rawQuery using v2 endpoints and Flux parsing only.
   - Removes any concept of `api_version`.

2. InfluxDBv2Config (extends AbstractDriverConfiguration)
   - Fields: url, token, org, bucket, timeout, verify_ssl, debug, precision, connection_type='http' (optional), persistent_connection.
   - Drop `api_version` and any v1-only settings (e.g., `udp_port`).
   - `getClientConfig()` returns only v2-relevant options.

3. InfluxDBv2QueryBuilder
   - Flux query builder exclusively.
   - No InfluxQL capabilities.

4. InfluxDBv2RawQuery implements RawQueryInterface
   - Represents raw Flux queries.

5. InfluxDBv2 Schema Manager
   - `InfluxDBv2SchemaManager` uses Flux to list measurements, check existence, etc.
   - No branching on version.

6. Connection Layer
   - `TimeSeriesPhp\Drivers\InfluxDBv2\Connection\HttpConnectionAdapter` specialized for v2; no factory dispatch by version.
   - Commands live only under `Connection/Command/V2` with a simplified small factory or direct usage (no version switch). If factory is still helpful, name it `InfluxDBv2HttpCommandFactory` without version parameter.
   - `getOrgId()` resolves organization ID using `/api/v2/orgs` only; no v1 fallback.

7. Error Handling
   - All thrown exceptions should be specific and extend `TSDBException` (e.g., ConnectionException, WriteException, DatabaseException).

## Removal/Changes in Existing v1 Driver

- Remove v2 branches and v2 imports from:
  - `src/Drivers/InfluxDB/InfluxDBDriver.php`
  - `src/Drivers/InfluxDB/Schema/InfluxDBSchemaManager.php`
  - `src/Drivers/InfluxDB/Connection/HttpConnectionAdapter.php`
  - `src/Drivers/InfluxDB/Connection/Command/InfluxDBHttpCommandFactory.php` (keep V1 only)
- Remove `api_version` from `InfluxDBConfig` and related logic; v1 driver will assume v1 behavior.
- Any Flux CSV parsing inside the v1 driver should be deleted; v1 driver should parse InfluxQL responses only.

## Dependency Injection and Registration

- New driver attribute:
  ```php
  #[Driver(
      name: 'influxdbv2',
      queryBuilderClass: InfluxDBv2QueryBuilder::class,
      configClass: InfluxDBv2Config::class,
      schemaManagerClass: Schema\InfluxDBv2SchemaManager::class,
  )]
  class InfluxDBv2Driver extends AbstractTimeSeriesDB { ... }
  ```
- Ensure service discovery/autowiring picks up the new driver class; follow existing patterns in DriverManager if any registration is necessary.
- Update `config/timeseries.php` example to include a separate configuration section for `influxdbv2` and adjust the existing `influxdb` entry to represent v1.

## Tests

- Duplicate and adapt existing InfluxDB tests:
  - `tests/Drivers/InfluxDB` remains, adjusted to v1-only expectations.
  - New `tests/Drivers/InfluxDBv2` covering:
    - Connection and health checks
    - Write and writeBatch
    - Raw Flux queries and parsing
    - Schema management via Flux
    - Error handling and edge cases
- Update integration test script `bin/run-integration-tests.sh` to separately run v1 and v2 driver tests if required.

## Migration Guide (for library users)

- If using v1 (InfluxQL): keep using driver name `influxdb`. Remove any `api_version` option from your configuration; use v1-compatible options only.
- If using v2 (Flux): change driver name to `influxdbv2`. Move configuration to `influxdbv2` section and remove v1-only options (e.g., `udp_port`).
- Code that previously relied on `getApiVersion()` should instead depend on the chosen driver. Avoid branching.

## Step-by-Step Execution Plan

Phase 0: Prep
1. Add the new directory `src/Drivers/InfluxDBv2` with skeleton classes and namespaces. (no behavior changes)
2. Copy v2-relevant code from the current implementation as a baseline into the new driver, keeping namespaces updated.

Phase 1: v2 Driver Bring-Up
3. Implement `InfluxDBv2Config` without `api_version` and v1-only settings.
4. Implement `InfluxDBv2QueryBuilder` (Flux only) and `InfluxDBv2RawQuery`.
5. Implement `InfluxDBv2SchemaManager` by extracting the v2 code paths from `InfluxDBSchemaManager`.
6. Implement `InfluxDBv2Connection` and V2 Commands by moving code from `Connection/Command/V2/*` to `InfluxDBv2/...`.
7. Implement `InfluxDBv2Driver` by extracting v2 code from `InfluxDBDriver` (Flux CSV parsing, write, query, delete, health, etc.).
8. Add unit tests for the new driver; duplicate relevant tests and adjust expectations and namespaces.

Phase 2: v1 Driver Cleanup
9. Remove v2 code paths from `InfluxDB` classes (commands factory, adapters, schema manager, driver, config) and delete `api_version` usages.
10. Ensure `InfluxDB` driver works with v1-only tests passing.

Phase 3: Documentation and Examples
11. Update `docs/` and `examples/` to demonstrate both drivers separately.
12. Update `README.md` reference and `config/timeseries.php` to show `influxdb` (v1) and `influxdbv2` entries.

Phase 4: QA
13. Run and fix PHPStan issues.
14. Run and fix Pint formatting.
15. Run PHPUnit for unit and integration tests; ensure both driver suites pass.

## Acceptance Criteria

- The repository contains a new `InfluxDBv2` driver with clean v2-only code and no references to v1 or `api_version` branching.
- The existing `InfluxDB` driver contains v1-only logic with no residual v2 code.
- All tests pass: core, v1, and new v2 suites.
- Documentation clearly explains how to use each driver and configure them.
- Static analysis (PHPStan) passes at the configured level.
- Code style (Pint) passes.

## Risk and Mitigation

- Risk: Hidden coupling between v1 and v2 shared helpers.
  - Mitigation: Duplicate minimal shared parsing or extract common neutral utilities into `src/Core` if truly generic.
- Risk: Missed namespace updates causing autowiring failures.
  - Mitigation: Search for all v2 class references and update imports; add targeted tests.
- Risk: Breaking user configs relying on `api_version`.
  - Mitigation: Provide clear migration notes; optionally accept `api_version` temporarily in v1 config with a deprecation warning, but prefer removal.

## Technical Notes

- Keep Symfony autoconfiguration enabled for the new driver; attributes should be sufficient for registration.
- Avoid generic `\Exception`; use domain exceptions extending `TSDBException`.
- Ensure QueryResult parsing for Flux is isolated under the v2 driver; do not leak this into v1.
- No `eval()` or `match(true)`; use explicit control flow.
- Prefer constructor property promotion and readonly where possible (PHP 8.2).

## Work Items Checklist

- [ ] Create `src/Drivers/InfluxDBv2/*` skeletons with namespaces and attributes.
- [ ] Move/copy v2 commands and HTTP adapter logic; remove factory version switching in v2.
- [ ] Implement `InfluxDBv2Config`, drop `api_version` and v1-only fields.
- [ ] Implement Flux-only query builder and raw query classes.
- [ ] Implement `InfluxDBv2SchemaManager` (Flux-only).
- [ ] Implement `InfluxDBv2Driver` with Flux CSV parsing.
- [ ] Clean up `InfluxDB` (v1) by removing v2 paths and `api_version`.
- [ ] Update tests: add `tests/Drivers/InfluxDBv2`, keep `tests/Drivers/InfluxDB` v1-only.
- [ ] Update config examples, README, and docs.
- [ ] Run phpunit/phpstan/pint and resolve issues.

## Timeline (suggested)

- Phase 0–1: 2–3 days (coding and initial tests)
- Phase 2: 1–2 days (cleanup and stabilization)
- Phase 3: 0.5 day (docs)
- Phase 4: 0.5–1 day (QA)

