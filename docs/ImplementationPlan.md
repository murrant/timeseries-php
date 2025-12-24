# Time-Series Abstraction Layer – Implementation Plan

This document describes a phased, production-grade implementation plan for a backend-neutral time-series ingestion and graphing system supporting multiple TSDBs (Prometheus, InfluxDB, RRD, etc.).

The plan assumes:
- One value per metric
- Labels only (no fields)
- Shared semantic model for ingestion and graphing
- TSDBs implemented as compiler + runtime drivers

---

## Phase 0 – Ground Rules & Invariants

### Hard Invariants
- A `MetricIdentifier` represents **exactly one numeric value over time**
- All dimensionality is expressed via **labels**
- Ingestion and graphing share the same metric definitions
- No backend concepts appear above the driver boundary
- Drivers must fail explicitly for unsupported features

Document these rules and enforce them via types and validation.

---

## Phase 1 – Core Domain Model (Backend-Neutral)

### 1.1 MetricIdentifier

**Purpose:** Canonical semantic identity of a metric

```php
MetricIdentifier
- namespace (string)
- name (string)
- unit (string|null)
- type (MetricType enum)
- backendMap (optional)
````

Deliverables:

* `MetricType` enum (COUNTER, GAUGE, HISTOGRAM, SUMMARY)
* Validation rules (naming, allowed aggregations)
* Serialization support (JSON/YAML)

---

### 1.2 Metric Registry

**Purpose:** Single source of truth for all metrics

Deliverables:

* YAML-based registry loader
* Registry validation at boot
* Access via stable string keys (e.g. `network.port.octets.in`)
* Optional backend overrides

---

### 1.3 MetricSample

**Purpose:** Generic ingestion record

```php
MetricSample
- MetricIdentifier
- labels (array<string, scalar>)
- value (float|int)
- timestamp (DateTimeImmutable)
```

Deliverables:

* Type enforcement
* Counter monotonicity checks
* Label normalization

---

### 1.4 Graph Definition Model

**Objects**

* `GraphDefinition`
* `SeriesDefinition`
* `TimeRange`
* `Resolution`
* `Aggregation`
* `GraphStyle`

Deliverables:

* Immutable value objects
* YAML/JSON representation
* Validation rules (metric type vs aggregation)
* Optional driver-extension support

---

### 1.5 TimeSeriesResult

**Purpose:** Backend-normalized query result

```php
TimeSeriesResult
- TimeRange
- Resolution
- TimeSeries[]
```

```php
TimeSeries
- MetricIdentifier
- labels
- DataPoint[]
```

```php
DataPoint
- timestamp (int)
- value (float|int|null)
```

Deliverables:

* Normalization rules
* Serialization helpers
* Null-handling guarantees

---

## Phase 2 – Driver Interfaces (Hard Boundary)

### 2.1 Core Interfaces

Deliverables:

```php
TsdbIngestor
- write(MetricSample)

GraphCompiler
- compile(GraphDefinition): CompiledQuery

TsdbClient
- query(CompiledQuery): TimeSeriesResult

TsdbCapabilities
- supportsRate()
- supportsHistogram()
- supportsLabelJoin()
```

Rules:

* No backend types cross this boundary
* All validation happens here
* All backend quirks are hidden

---

### 2.2 CompiledQuery

Deliverables:

* Opaque interface or marker class
* Backend-specific implementations
* Immutable

---

## Phase 3 – First Backend Implementation (RRD or Prometheus)

Choose **one** backend first to validate architecture.

### 3.1 Directory Structure

```
Drivers/<Backend>/
  <Backend>Compiler.php
  <Backend>Client.php
  <Backend>Ingestor.php
  <Backend>Capabilities.php
```

---

### 3.2 Ingestor Implementation

Deliverables:

* MetricIdentifier → backend schema mapping
* Label → path/tag mapping
* Batch support (optional)
* Error normalization

---

### 3.3 Compiler Implementation

Deliverables:

* GraphDefinition → CompiledQuery
* Aggregation mapping
* Rate math
* Fan-out expansion
* Capability checks
* Driver-extension handling

---

### 3.4 Client Implementation

Deliverables:

* Backend transport
* Authentication handling
* Retry logic
* Raw response → TimeSeriesResult normalization

---

## Phase 4 – Laravel Integration

### 4.1 Service Container Binding

Deliverables:

* Config-based driver selection
* Bindings for:

    * `TsdbIngestor`
    * `GraphCompiler`
    * `TsdbClient`
    * `TsdbCapabilities`

---

### 4.2 Environment Configuration

Deliverables:

* `config/tsdb.php`
* Per-driver connection config
* Feature flags

---

## Phase 5 – Graphing Pipeline

### 5.1 Graph Service

```php
GraphService
- render(GraphDefinition): TimeSeriesResult
```

Deliverables:

* Compiler invocation
* Client invocation
* Error propagation
* Result validation

---

### 5.2 Transformation Layer (Optional)

Deliverables:

* Rate normalization
* Unit conversion
* Series stacking
* Label aggregation

Kept above drivers.

---

## Phase 6 – Ingestion Pipeline

### 6.1 Collector / Poller Integration

Deliverables:

* MetricSample creation
* Registry lookup
* Label consistency enforcement
* Write batching

---

### 6.2 Backpressure & Error Handling

Deliverables:

* Drop / retry strategies
* Health indicators
* Logging and metrics

---

## Phase 7 – Second Backend Implementation

Add a second TSDB to validate abstraction.

Deliverables:

* No changes above driver boundary
* Comparative tests
* Capability differences surfaced

---

## Phase 8 – Testing Strategy

### 8.1 Unit Tests

* Registry validation
* Compiler output
* Ingestor mapping
* Capability enforcement

---

### 8.2 Golden Graph Tests

* Same GraphDefinition
* Different drivers
* Equivalent TimeSeriesResult

---

### 8.3 Contract Tests

* Driver compliance with interfaces
* Failure mode validation

---

## Phase 9 – Tooling & UX

### 9.1 Schema Export

Deliverables:

* Metric catalog API
* Graph schema API

---

### 9.2 UI Support

Deliverables:

* Capability-aware graph editors
* Metric discovery
* Label suggestions

---

## Phase 10 – Operational Hardening

### 10.1 Performance

* Query caching
* Resolution downsampling
* Batch writes

---

### 10.2 Observability

* Internal metrics
* Driver health checks
* Error classification

---

## Success Criteria

The implementation is considered complete when:

* A graph can be defined once and rendered on multiple TSDBs
* Metrics can be ingested without backend-specific logic
* Adding a new backend requires no changes above drivers
* Backend removal requires only config changes
* Unsupported features fail loudly and deterministically

---

## Final Note

This plan deliberately prioritizes **semantic correctness and long-term maintainability** over short-term convenience. If implemented as described, the system will scale in both complexity and longevity without architectural decay.

```
```
