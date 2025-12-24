## 1. The Core Idea (One Sentence)

> You define **metrics and graphs in a semantic, backend-neutral language**, and each TSDB driver acts as a **compiler + runtime** for that language.

Everything else follows from that.

---

## 2. The Three Semantic Layers

### Layer 1: **Domain Model (Backend-Neutral)**

This layer describes *intent*.

**Key objects**

* `MetricIdentifier` – *what is measured*
* `MetricSample` – *a single observation*
* `GraphDefinition` – *what you want to see*
* `SeriesDefinition` – *which subset of data*
* `Aggregation` / `MetricType` – *how it is interpreted*
* `TimeSeriesResult` – *evaluated data*

**Properties**

* No TSDB concepts
* No query languages
* Serializable
* Shared by ingestion and graphing

This layer never changes when you add or replace a backend.

---

### Layer 2: **Driver Boundary (Hard Interface)**

This is the seam.

**Interfaces**

* `TsdbIngestor`
* `GraphCompiler`
* `TsdbClient`
* `TsdbCapabilities`

**Responsibilities**

* Validate semantics
* Translate generic → backend
* Normalize backend → generic

Nothing crosses this boundary untransformed.

---

### Layer 3: **Backend Implementations (Replaceable)**

One directory per TSDB:

```
Drivers/Prometheus/
Drivers/Influx/
Drivers/Rrd/
```

Each driver:

* owns naming conventions
* owns schema layout
* owns query syntax
* owns performance tuning
* fails explicitly when unsupported

Drivers are self-contained and swappable.

---

## 3. End-to-End Data Flow

### Ingestion Path

```
Poller / Collector
        ↓
 MetricSample (generic)
        ↓
 TsdbIngestor (driver)
        ↓
 Backend storage
```

* `MetricIdentifier` defines schema
* labels define dimensionality
* driver maps to backend reality

No graph knowledge required.

---

### Graphing Path

```
GraphDefinition (generic)
        ↓
 GraphCompiler (driver)
        ↓
 CompiledQuery (opaque)
        ↓
 TsdbClient
        ↓
 TimeSeriesResult (generic)
```

* Graph intent stays generic
* Compilation is deterministic
* Execution is backend-specific
* Results are normalized

No ingestion knowledge required.

---

## 4. The Unifying Contract: MetricIdentifier

This is the keystone.

```
MetricIdentifier
 ├── namespace
 ├── name
 ├── unit
 └── type
```

It:

* defines storage layout
* defines valid aggregations
* defines graph semantics
* defines ingestion constraints

If it is wrong, everything is wrong.

---

## 5. Labels: One Concept, Many Realizations

**Generic meaning**

> “Which instance of the metric?”

**Backend realizations**

* Prometheus: labels
* Influx: tags
* RRD: path segments
* Graphite: hierarchy

Drivers decide how labels are materialized.

---

## 6. One Value per Metric (The Simplifier)

This single decision:

* removes field complexity
* aligns all TSDBs
* simplifies graphing
* simplifies ingestion
* simplifies validation

Multiple values = multiple metrics.

---

## 7. Capability-Driven Design

Not all backends support all features.

Capabilities are:

* explicit
* checked early
* surfaced to UI

No silent degradation.

---

## 8. Driver-Specific Functionality (Controlled Escape Hatches)

Allowed via:

* namespaced extensions
* typed extension objects
* compiler-level validation

Never via conditionals in core logic.

---

## 9. Where Logic Lives (Important)

| Concern            | Lives Where         |
| ------------------ | ------------------- |
| Metric meaning     | Registry            |
| Validation         | Compiler / Ingestor |
| Naming conventions | Driver              |
| Query syntax       | Driver              |
| Rate math          | Compiler            |
| Transport / auth   | Client              |
| Stacking / legends | Above drivers       |

This separation is what keeps the system maintainable.

---

## 10. What Adding a New TSDB Looks Like

To add a backend you:

1. Implement:

   * `TsdbIngestor`
   * `GraphCompiler`
   * `TsdbClient`
   * `TsdbCapabilities`
2. Bind them in the container
3. Write tests

You do **not**:

* change ingestion code
* change graph definitions
* change UI logic
* change metric registry

That is the success criterion.

---

## 11. Failure Modes You Have Explicitly Avoided

* Leaky abstractions
* Lowest-common-denominator queries
* Stringly-typed metrics
* Backend conditionals everywhere
* Divergent ingestion and graph schemas
* Silent feature loss

---

## 12. The Mental Model (Final)

Think of the system as:

```
Metric + Graph Language
        ↓
   TSDB Compilers
        ↓
   TSDB Runtimes
```

Or more succinctly:

> **You are building a time-series language, not a database adapter.**

The databases are merely compilation targets.

---

## 13. Why This Scales

* New metrics do not require backend changes
* New backends do not require metric changes
* Graphs are portable
* Bugs are localized
* Testing is deterministic
