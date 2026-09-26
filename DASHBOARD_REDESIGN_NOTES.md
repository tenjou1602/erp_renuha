# Dashboard Redesign Notes

## Real widgets added

- Main dashboard: real project summary rows, date-based project progress, and completed-versus-remaining project gauge for Engineering users.
- Engineering dashboard: project status cards, project creation activity from `projects.created_at`, and completed-versus-remaining gauge.
- Accounting dashboard: existing invoice and financial totals, invoice activity from `invoices.created_at`, and paid-versus-remaining invoice gauge.
- Warehouse dashboard: existing inventory totals and stock movement activity from `stock_movements.created_at`.
- Existing module tables keep their real database rows and status values; shared CSS now presents them with softer dividers and status dots.

## Intentionally omitted

- Task checklist: omitted because the schema has no tasks or todos table.
- Fake trend percentages: omitted because no reliable comparison metric was added to the existing queries.
- Warehouse completion gauge: omitted because inventory and movement data do not provide a meaningful completion percentage.
- Generic workload-by-person chart: omitted because no consistent workload assignment dataset exists across departments.
- Procurement completion gauge: omitted because purchase-request-to-PO conversion is not a stable one-to-one metric in the current schema.
