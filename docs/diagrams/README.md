# Dromos architecture diagrams

These PlantUML diagrams document the paths a feature developer is most likely to change. They describe the intended architecture; keep them updated when a workflow, table, external integration, or authorization rule changes.

| Diagram | Use it when working on |
| --- | --- |
| [System architecture](system-architecture.puml) | Application layers, Google integrations, credentials, or provider implementations |
| [Data model](data-model.puml) | Migrations, models, ownership, stored results, or geocoding cache behavior |
| [Optimized route creation](optimized-route-sequence.puml) | The main route-creation workflow, geocoding, optimization, or persistence |
| [Sequential route batching](sequential-route-batching.puml) | Routes over 25 stops, ordered routing, batching limits, or polyline aggregation |
| [Route editing and recalculation](route-edit-recalculation.puml) | Drag-and-drop, manual ordering, adding/editing/deleting stops, or automatic recalculation |
| [Authentication and authorization](authentication-authorization.puml) | Login, registration, sessions, route ownership, middleware, or policies |

## Rendering

With PlantUML installed, render every diagram from the project root:

    plantuml docs/diagrams/*.puml

Use -tsvg if SVG output is preferred:

    plantuml -tsvg docs/diagrams/*.puml
