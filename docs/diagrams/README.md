# Dromos architecture diagrams

These PlantUML diagrams document the paths a feature developer is most likely to change. They describe the intended architecture; keep them updated when a workflow, table, external integration, or authorization rule changes.

| Diagram | Source | Use it when working on |
| --- | --- | --- |
| [System architecture](rendered/system-architecture.svg) | [PlantUML](src/system-architecture.puml) | Application layers, Google integrations, credentials, or provider implementations |
| [Data model](rendered/data-model.svg) | [PlantUML](src/data-model.puml) | Migrations, models, ownership, stored results, or geocoding cache behavior |
| [Optimized route creation](rendered/optimized-route-sequence.svg) | [PlantUML](src/optimized-route-sequence.puml) | The main route-creation workflow, geocoding, optimization, or persistence |
| [Sequential route batching](rendered/sequential-route-batching.svg) | [PlantUML](src/sequential-route-batching.puml) | Routes over 25 stops, ordered routing, batching limits, or polyline aggregation |
| [Route editing and recalculation](rendered/route-edit-recalculation.svg) | [PlantUML](src/route-edit-recalculation.puml) | Drag-and-drop, manual ordering, adding/editing/deleting stops, or automatic recalculation |
| [Authentication and authorization](rendered/authentication-authorization.svg) | [PlantUML](src/authentication-authorization.puml) | Login, registration, sessions, route ownership, middleware, or policies |

## Rendering

SVG files in `rendered/` are generated and committed automatically when a PlantUML source file is added or changed.

With PlantUML installed, render every diagram locally from the project root:

    plantuml -tsvg -o ../rendered docs/diagrams/src/*.puml
