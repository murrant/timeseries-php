## Boundaries

### What Goes Above Drivers
* Metric registry
* Graph definitions
* Label semantics
* Aggregation enums
* Validation rules
* UI logic

### What Goes Inside Drivers
* Naming conventions
* Schema layout
* Query syntax
* Path resolution
* Backend quirks
* Performance tuning

### What Must Never Cross the Boundary
* PromQL
* Flux
* RRD arguments
* Backend errors
* Backend data structures

Drivers translate those into generic failures or results.


## What we want to avoid
* Mega-driver interfaces
* Backend conditionals in services
* Stringly-typed queries
* Implicit feature support
* Accidental coupling between ingestion and graphing
