# Technische Dokumentation - Innersense (PHP + MariaDB)

## 1. Ziel und Scope
Diese Anwendung kombiniert:

- eine oeffentliche Website fuer Marketing, Leistungsdarstellung und Kontaktanfragen
- eine passwortgeschuetzte Verwaltungsoberflaeche fuer Kunden, Fahrzeuge, Anfragen, Termine, Kalender und Stammdaten
- einen FTP-basierten Deployment-Workflow fuer geaenderte Dateien

Die Dokumentation beschreibt Architektur, Laufzeit, Datenmodell, Konfiguration, Sicherheitsmechanismen und Betriebsablaeufe.

## 2. Technologie-Stack

- Backend: PHP (prozedural + einzelne Service-/Repository-Klassen)
- Datenbank: MariaDB (InnoDB, utf8mb4)
- Webserver lokal/containerisiert: Apache (php:8.3-apache)
- Frontend: serverseitig gerenderte PHP-Seiten, CSS, wenig Vanilla-JS
- Container/Local Dev: Docker Compose (optional)
- Deployment: Python-Skripte per FTP

## 3. Laufzeitarchitektur

### 3.1 Schichten

- Praesentationsschicht: PHP-Dateien unter public/
- Domain-/Datenzugriff:
  - src/CustomerRepository.php
  - src/EmailTemplateService.php
  - src/FormBotProtection.php
- Konfiguration/Bootstrap:
  - config/env.php (ENV-Loader)
  - config/database.php (PDO-Factory)
  - public/bootstrap.php (Repository-Initialisierung)

### 3.2 Request-Flows (vereinfacht)

1. HTTP-Request trifft eine Datei in public/
2. Datei bindet i. d. R. Konfiguration und DB ein
3. Daten werden gelesen/validiert/geschrieben
4. HTML-Ausgabe oder Redirect (Post/Redirect/Get)

### 3.3 Session und Authentisierung

- Admin-Login und Session-Handling laufen in public/verwaltung.php
- Session-Timeout: 600 Sekunden Inaktivitaet
- Passwortpruefung via password_verify
- CSRF-Schutz in Admin-POST-Formularen ueber Token in Session

## 4. Verzeichnisstruktur und Verantwortlichkeiten

- public/: Webroot, Endpunkte und statische Assets
- src/: wiederverwendbare Logik (Repository/Services/Helper)
- config/: Laufzeitkonfiguration, Auth-Hash, ENV-Loader, DB-Zugriff
- sql/: Basisschema + Migrationen
- scripts/: Deployment-Hilfsskripte
- mariadb_migration/: Migrations-/Server-Zugangskonfiguration
- docs/: Projektdokumentation
- tmp/: Laufzeitdateien (z. B. Rate-Limit-State)

## 5. Endpunkte (fachlich)

### 5.1 Oeffentliche Seiten

- public/index.php: Startseite, Kontakt-Wizard, E-Mail-Versand, Slot-Abfrage (AJAX)
- public/preise.php, public/kontakt.php, public/agb.php, public/datenschutz.php, public/impressum.php, public/widerrufsbelehrung.php: statische/halb-statische Inhalte

### 5.2 Verwaltungsbereich

- public/verwaltung.php: Login, Dashboard, Listen, Settings
- public/customer_detail.php, public/customer_create.php: Kundenpflege
- public/vehicle_detail.php: Fahrzeugpflege
- public/request_detail.php: Anfragepflege
- public/appointment_detail.php: Terminpflege
- public/workload_reference_detail.php: Zeit-/Preis-Referenzen
- public/calendar.php: Kalenderansichten + Blockierung/Freigabe von Slots

### 5.3 Legacy/Parallelpfad

- public/customers.php, public/customer_edit.php, public/customer_delete.php
- nutzt Tabelle customers (Plural) via src/CustomerRepository.php
- grosser Teil der aktuellen Verwaltung nutzt hingegen customer/customer_vehicle/customer_request
- dadurch existieren zwei Datenpfade im Bestand

## 6. Datenbankmodell

### 6.1 Kernobjekte

- customer: Stammdaten Kunde
- customer_vehicle (alternativ customer_vehicles): Fahrzeuge je Kunde
- customer_request (alternativ customer_requests): Anfragen/Auftraege
- cleaning_package: konfigurierbare Pakete
- vehicle_type_option: konfigurierbare Fahrzeugtypen
- workload_reference: Zeit-/Preisreferenz je Paket + Fahrzeugtyp
- schedule_blocked_slot: manuell gesperrte Slots
- email_template + email_type_template_assignment: E-Mail-Templates und Zuordnung
- site_page_hit: Seitenaufrufe (Counter)

Basisschema: sql/schema.sql
Migrationen: sql/migrations/*.sql

### 6.2 Schema-Strategie

- Mischung aus statischem SQL-Schema und Runtime-Guardrails
- mehrere Endpunkte erstellen benoetigte Tabellen bei Bedarf (CREATE TABLE IF NOT EXISTS)
- bestimmte Kompatibilitaetsanpassungen passieren zur Laufzeit (z. B. nullable email in customer)

## 7. Konfiguration, Secrets und Berechtigungsdateien

Dieser Abschnitt beantwortet explizit, wo DB- und Server-Berechtigungen liegen.

### 7.1 Datenbankzugang (Runtime)

1. config/.env
- Enthaelt DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS bzw. DB_PASSWORD
- wird durch config/env.php geladen
- wird durch config/database.php ausgewertet

2. config/database.php
- baut die PDO-Verbindung auf
- definiert Fallback-Werte fuer lokale Entwicklung

3. src/config/db.php
- alternative PDO-Erzeugung aus ENV-Variablen (DB_PASSWORD)
- aktuell weniger zentral als config/database.php, aber im Repository vorhanden

### 7.2 Admin-Zugang Verwaltung

1. config/verwaltung_auth.php
- speichert den Passwort-Hash fuer die Verwaltungsanmeldung
- Format: PHP-Array mit Schluessel password_hash
- wird in public/verwaltung.php gelesen/geschrieben

### 7.3 Server- und Deployment-Zugaenge

1. mariadb_migration/server_credentials.conf
- zentrale Zugangsdaten fuer FTP/Server + DB-Migration
- wird genutzt von scripts/deploy_settings.py und scripts/deploy_changed_files.py
- enthaelt typischerweise SERVER_HOST, SERVER_USER, SERVER_PASSWORD, SERVER_PATH, DB_* usw.

2. scripts/deploy_changed_files.py
- liest Credentials aus mariadb_migration/server_credentials.conf
- uebertraegt geaenderte Dateien per FTP auf den Zielserver

3. scripts/deploy.sh
- Wrapper fuer deploy_changed_files.py

### 7.4 Weitere Konfigurationsdateien

- config/env.php: parser fuer .env-Dateien
- optional config/public_contact.php: Legacy-Konfiguration fuer Kontakt-/Social-Daten

## 8. Technische Berechtigungsanforderungen

### 8.1 Datenbankrechte fuer den App-User

Mindestens erforderlich:

- SELECT, INSERT, UPDATE, DELETE auf den genutzten Tabellen
- CREATE TABLE auf Tabellen, die zur Laufzeit abgesichert werden

Situativ erforderlich:

- ALTER TABLE (z. B. wenn customer.email auf NULL umgestellt wird)

Empfehlung:

- fuer Produktion Migrationen vorab mit Admin-DB-User ausfuehren
- App-User danach auf noetige DML-Rechte und nur notwendige DDL reduzieren

### 8.2 Dateisystemrechte im Projekt/Webroot

- Schreibrechte auf config/ sind notwendig fuer:
  - Passwortwechsel (config/verwaltung_auth.php)
  - Speichern von Social-/Kontaktdaten in .env
- Schreibrechte auf tmp/ fuer Form-Rate-Limiting (tmp/form_rate_limit.json)

Code-seitige Beobachtung:

- public/verwaltung.php setzt nach Schreiben von Auth-/Settings-Dateien chmod 0640

### 8.3 Session- und Laufzeitumgebung

- Session-Storage muss serverseitig funktionieren (PHP-Session-Handler)
- Mailversand nutzt PHP mail(); MTA/SMTP auf Zielsystem muss konfiguriert sein

## 9. Sicherheit (Ist-Zustand)

### 9.1 Positive Mechanismen

- Passwort-Hashing mit password_hash/password_verify
- CSRF-Token in Admin-POST-Formularen
- Session-Timeout fuer Verwaltungsbereich
- Bot-Schutz oeffentlicher Formulare:
  - Honeypot
  - Mindest-Ausfuellzeit
  - IP-basiertes Rate-Limit
  - optional Cloudflare Turnstile
- PDO mit vorbereiteten Statements

### 9.2 Risiken/Schwaechen im Bestand

- Sensible Zugangsdaten liegen in Dateien im Projekt (insb. server_credentials.conf)
- Legacy- und neues Datenmodell parallel (customers vs customer)
- Teilweise DDL im Runtime-Pfad statt reinem Migrationsprozess
- Keine dedizierte Rollen-/Rechteverwaltung innerhalb der Admin-UI

### 9.3 Mindestmassnahmen (kurzfristig)

- Secrets rotieren und in einen Secret-Store/CI-Variablen verschieben
- server_credentials.conf strikt ausserhalb von Repo/Backups halten
- Schreibrechte auf config/ minimal halten
- DDL in kontrollierte Migrationen verlagern

## 10. Deployment und Betrieb

### 10.1 Lokaler Betrieb

Option A: Docker
- docker compose up -d --build
- Schema importieren (falls noetig) ueber mariadb-Client

Option B: Native PHP
- PHP-Server: php -S localhost:8080 -t public
- DB vorher anlegen und sql/schema.sql ausfuehren

### 10.2 Deployment-Workflow (aktueller Stand)

- scripts/deploy_changed_files.py liest git status --porcelain
- nur relevante Dateitypen/Ordner werden uebertragen
- Zielbasis wird aus SERVER_PATH bzw. DOCUMENT_ROOT abgeleitet
- Upload erfolgt per FTP (Binary-Transfer)

## 11. Bekannte technische Besonderheiten

- Tabellennamen werden an mehreren Stellen dynamisch erkannt:
  - customer_vehicle vs customer_vehicles
  - customer_request vs customer_requests
- E-Mail-Templates werden bei Bedarf automatisch angelegt und mit Defaults befuellt
- Kalenderberechnung beruecksichtigt gesperrte Slots und bestehende Buchungen
- iCal-Export wird in der Verwaltung dynamisch erzeugt

## 12. Konkrete Fundstellen fuer DB- und Server-Berechtigungen

Schnelluebersicht der wichtigsten Dateien:

- config/.env
  - Laufzeit-DB-Zugangsdaten und App-Umgebungswerte
- config/database.php
  - technische PDO-Verbindungslogik
- config/verwaltung_auth.php
  - Admin-Login-Hash
- mariadb_migration/server_credentials.conf
  - Server-/FTP- und DB-Credentials fuer Deployment/Migration
- scripts/deploy_changed_files.py
  - Verwendungsstelle der Server-Credentials
- scripts/deploy_settings.py
  - weitere Verwendungsstelle der Server-Credentials

## 13. Wartungsempfehlung

- Mittelfristig den Legacy-Pfad customers vereinheitlichen oder klar trennen
- Runtime-DDL reduzieren und in Migrationen verschieben
- Security-Hardening dokumentieren (Backups, Monitoring, Rotation, Least Privilege)
- Automatisierte Tests aufbauen (tests/ ist aktuell leer)
