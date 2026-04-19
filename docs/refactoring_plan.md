# Refactoring Plan

Stand: 2026-04-19

## Ziele

- Reduktion von Duplikaten und Kopplung in `public/`.
- Vereinheitlichung des Datenmodells auf `customer`, `customer_vehicle`, `customer_request`.
- Verlagerung von Schema-Aenderungen aus Runtime-Endpunkten in SQL-Migrationen.
- Verbesserte Wartbarkeit durch klarere Service-Schicht in `src/`.

## Phase 1 - Sichere erste Schritte (laufend)

- [x] Gemeinsame Tabellen-Erkennung in `src/` eingefuehrt (`src/TableResolver.php`).
- [x] Erste Endpunkte auf gemeinsamen Resolver umgestellt:
  - `public/request_detail.php`
  - `public/appointment_detail.php`
  - `public/calendar.php`
  - `public/verwaltung.php`
- [x] Weitere duplicated helper fuer Slots/Kalender zentralisieren (index, kontakt, calendar -> src/ScheduleSlots.php).
- [ ] Kleinere Smoke-Checks fuer Kernflows als Skript dokumentieren.

## Phase 2 - Runtime-DDL abbauen

- [x] Runtime-DDL inventarisiert (siehe `docs/runtime_ddl_inventory.md`).
- [x] Erste fehlende Struktur in SQL-Migrationen ueberfuehrt (`site_page_hit`) und `customer.email` nullable-Migration vorbereitet.
- [x] Runtime-DDL in Endpunkten entfernen (keine `CREATE TABLE IF NOT EXISTS`/`ALTER TABLE` mehr in `public/` und `src/`; Schemaaenderungen laufen ueber `sql/migrations`).

## Phase 3 - Datenmodell konsolidieren

- [x] Legacy-Pfad `customers` auf `customer` migrieren (CustomerRepository auf `customer` umgestellt; SQL-Migration fuer `customer_typ`/`company_name` erstellt; Legacy-UI-Endpunkte auf kanonische Verwaltung umgeleitet).
- [x] `CustomerRepository` auf kanonisches Modell umstellen (Basis: Tabelle `customer`, mit optionaler Spaltenkompatibilitaet fuer Legacy-UI-Felder).
- [x] Tabellen-Erkennung fuer Singular/Plural im `public/`-Pfad entfernt (kanonisch: `customer_request`, `customer_vehicle`).

Status: Phase 3 abgeschlossen.

## Phase 4 - Verwaltungsmodul entkoppeln

- [x] `public/verwaltung.php` in Controller + View-Partial + Services aufteilen (`src/AdminActionService.php`; `public/partials/admin_main_panels.php`; `public/partials/admin_settings_panels.php`).
- [x] Settings-Funktionen in eigene Service-Klassen verlagern (`src/AdminCatalogService.php`).
- [x] Query-Building und Tabellen-Render-Funktionen trennen (`src/AdminDashboardDataService.php`, `public/partials/admin_table_bodies.php`).

## Exit-Kriterien

- Keine DDL-Statements mehr in HTTP-Request-Pfaden.
- Keine parallelen Kernmodelle (`customers` vs `customer`) mehr im Produktivpfad.
- Deutlich kleinere und thematisch getrennte Dateien unter `public/`.
