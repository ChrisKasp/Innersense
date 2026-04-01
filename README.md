# Innersense Webseite + Kundenverwaltung (PHP + MariaDB)

Dieses Projekt kombiniert eine gestylte Marketing-Startseite mit einer einfachen Kundenverwaltung auf Basis von PHP (PDO) und MariaDB.

## Funktionen

- Moderne Startseite im Stil einer Preis-Uebersicht
- Kunden anlegen
- Kunden bearbeiten
- Kunden loeschen
- Kundenliste durchsuchen

## Routen

- `http://localhost:8080/` Startseite (Preise / Weboberflaeche)
- `http://localhost:8080/customers.php` Kundenverwaltung

## Projektstruktur

- `public/` Web-Root mit Seiten und CSS
- `src/` PHP-Logik (Repository, Helper)
- `config/` DB- und Env-Konfiguration
- `sql/schema.sql` Datenbank-Schema
- `docker-compose.yml` Docker-Setup fuer App + MariaDB

## Schnellstart mit Docker

1. `.env.example` nach `config/.env` kopieren.
2. Container starten:

```bash
docker compose up -d --build
```

3. Schema importieren:

```bash
docker compose exec -T db mariadb -uinnersense_user -pinnersense_pass innersense < sql/schema.sql
```

4. Webseite oeffnen:

- `http://localhost:8080`

## Alternative ohne Docker

Voraussetzungen:

- PHP 8.2+
- MariaDB

Schritte:

1. `.env.example` nach `config/.env` kopieren und DB-Daten anpassen.
2. Datenbank und Tabelle mit `sql/schema.sql` erstellen.
3. PHP Dev-Server starten:

```bash
php -S localhost:8080 -t public
```

4. Webseite oeffnen unter `http://localhost:8080`.

## Hinweis zur Sicherheit

Dies ist ein bewusst schlankes Starter-Projekt. Fuer Produktion sollten u. a. noch CSRF-Schutz, robustere Validierung, Authentifizierung, Logging und Backups ergaenzt werden.
