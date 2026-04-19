# Runtime DDL Inventory

Stand: 2026-04-19

## Ziel

Dieses Dokument listet DDL im Runtime-Pfad (HTTP-Requests) und definiert die Reihenfolge zur Auslagerung in SQL-Migrationen.

## Gefundene Runtime-DDL

### CREATE TABLE IF NOT EXISTS

- Keine verbleibenden `CREATE TABLE IF NOT EXISTS` mehr in `public/` oder `src/`.

### ALTER TABLE

- Keine mehr im `public/`-Runtime-Pfad fuer `customer.email` (in Migration ueberfuehrt und aus Endpunkten entfernt).

## Abgleich mit SQL-Basis

In `sql/schema.sql` sind fast alle oben genannten Tabellen bereits enthalten. Nicht enthalten ist `site_page_hit`.

## Erste Migration (in diesem Schritt vorbereitet)

Neue Migration: `sql/migrations/20260419_add_site_page_hit_and_customer_email_nullable.sql`

Inhalt:
- Fuegt `site_page_hit` als persistente Tabellenstruktur hinzu.
- Setzt `customer.email` auf nullable (`VARCHAR(190) NULL`), damit Runtime-ALTER entfallen kann.
- Normalisiert leere E-Mail-Werte (`''`) auf `NULL`.

## Naechste Umsetzungsschritte

1. Migration in Staging/Produktion ausrollen.
2. Runtime-DDL-Abbau abgeschlossen. Weitere Schemaaenderungen nur noch ueber `sql/migrations`.
