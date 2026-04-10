# Fachliche Dokumentation – Innersense Autoreinigung Kundenverwaltung

**Dokumentversion:** 1.0  
**Stand:** April 2026  
**Anwendung:** Innersense – Webseite + Kundenverwaltungssystem

---

## Inhaltsverzeichnis

1. [Überblick](#überblick)
2. [Geschäftliche Zwecke und Funktionen](#geschäftliche-zwecke-und-funktionen)
3. [Benutzer und Benutzerrollen](#benutzer-und-benutzerrollen)
4. [Funktionale Anforderungen](#funktionale-anforderungen)
5. [Datenbankmodell](#datenbankmodell)
6. [Geschäftsprozesse](#geschäftsprozesse)
7. [Sicherheit und Datenschutz](#sicherheit-und-datenschutz)
8. [Benutzerschnittstellen](#benutzerschnittstellen)
9. [Integration und Konfiguration](#integration-und-konfiguration)
10. [Betrieb und Wartung](#betrieb-und-wartung)

---

## 1. Überblick

Die **Innersense Kundenverwaltung** ist ein integriertes System zur Verwaltung von Kundeninformationen, Fahrzeugen, Reinigungsaufträgen und Terminen für ein Autoreinigungsunternehmen. Die Anwendung besteht aus zwei Kernkomponenten:

### 1.1 Komponenten

| Komponente | Zweck | Zielgruppe |
|---|---|---|
| **Öffentliche Website** | Marketing, Preisdarstellung, Online-Kontaktanfragen | Bestands- und Interessentenkunden |
| **Verwaltungsoberfläche** | Kundenpflege, Fahrzeugverwaltung, Terminplanung, Auftragsverwaltung | Innerbetriebliches Personal |
| **Datenbankschicht** | Persistierung aller Geschäftsdaten | Applikation |

### 1.2 Geschäftliche Bedeutung

Die Anwendung unterstützt das Kerngeschäft durch:

- **Kundenakquisition**: Kontaktanfragen über die öffentliche Website
- **Kundenmanagement**: Verwaltung von Kundendaten, Fahrzeuginventar und Kontakthistorie
- **Auftragsverwaltung**: Erfassung, Verfolgung und Abwicklung von Reinigungsaufträgen
- **Terminplanung**: Verwaltung von Reinigungsterminen und Ressourcenplanung
- **Preisoptimierung**: Flexible Konfiguration von Paketen und Preisen nach Fahrzeugtyp

---

## 2. Geschäftliche Zwecke und Funktionen

### 2.1 Öffentliche Website-Funktionen

Die öffentliche Website dient der **Kundenakquisition und Information**:

#### Startseite und Informationsseiten
- **Startseite** (`index.php`): Verbindung zu Leistungsangebot und Kontaktmöglichkeiten
- **Preisliste** (`preise.php`): Übersicht der Reinigungspakete und Preise
- **Kontakt** (`kontakt.php`): Direkte Kontaktanfrage-Erfassung
- **Allgemeine Geschäftsbedingungen** (`agb.php`)
- **Datenschutz** (`datenschutz.php`)
- **Impressum** (`impressum.php`)
- **Widerrufsbelehrung** (`widerrufsbelehrung.php`)

#### Funktionale Features
- Kontaktformular mit automatischer Bot-Protection und E-Mail-Versand
- AJAX-basierte Verfügbarkeitsprüfung für Reinigungstermine
- Responsive Design mit Bildern und visuellen Angeboten

### 2.2 Verwaltungsoberfläche-Funktionen

Die Verwaltungsoberfläche bietet passwortgeschützte Funktionen für Betriebspersonal:

#### Benutzerverwaltung
- Admin-Login mit Session-Management
- Automatischer Session-Timeout nach Inaktivität (600 Sekunden)
- Passwort-Sicherung via `password_verify()`
- CSRF-Token-Schutz für sensitive Operationen

#### Kundenverwaltung
- **Kundenliste**: Übersicht aller erfassten Kunden mit Suchfunktion
- **Kundenerstellung**: Erfassung neuer Kunden mit Adressinformationen
- **Kundenbearbeitung**: Aktualisierung von Kontaktdaten und Adresse
- **Kundenlöschung**: Entfernung obsoleter Kundeneinträge
- **Kundendetails**: Detaillierte Ansicht mit verknüpften Fahrzeugen und Aufträgen

#### Fahrzeugverwaltung
- Fahrzeugerfassung je Kunde (Marke, Modell, Typ, Kennzeichen)
- Fahrzeugtyp-Klassifizierung (Kleinwagen, Kompakt, Mittel, Oberklasse, etc.)
- Fahrzeugnotizen und Metadaten
- Automatische Zuordnung zu Reinigungspaketen

#### Auftragsmanagement
- **Anfrage-Erfassung**: Neue Anfragen von Website oder intern
- **Anfrage-Änderung**: Änderung von Paketen, Kundenwünschen und Status
- **Status-Verfolgung**: new → contacted → scheduled → completed/cancelled
- **Spezielle Wünsche**: Erfassung besonderer Anforderungen pro Auftrag
- **Preisermittlung**: Automatische Preisberechnung basierend auf Paket und Fahrzeugtyp

#### Terminplanung
- **Kalenderansicht**: Tagesweise und wochenweise Zeitslot-Übersicht
- **Terminergänzung**: Buchung von konkreten Terminen zu Aufträgen
- **Slot-Blockierung**: Manuelle Sperrung von nicht verfügbaren Zeiten
- **Verfügbarkeitsprüfung**: Ausweis belegter und verfügbarer Slots

#### Konfiguration (Stammdaten)
- **Reinigungspakete**: Definition von Angeboten (Basis, Intensiv, etc.)
- **Fahrzeugtypen**: Klassifizierung von Fahrzeugtypen
- **Workload-Referenzen**: Zeitaufwand und Netto-Preise je Paket/Fahrzeugtyp-Kombination
- **E-Mail-Templates**: Vordefinierte E-Mail-Vorlagen mit Platzhaltern

### 2.3 E-Mail-Funktionalität

Die Anwendung kann automatisiert E-Mails versenden:

#### Template-Arten
- **Kontakt-Anfrage-Benachrichtigung**: Intern beim Absenden einer Anfrage
- **Kontakt-Anfrage-Bestätigung**: an den Anfragsteller

#### Placeholder-System
E-Mail-Templates unterstützen dynamische Inhalte durch Platzhalter:
- `customer.*`: Kundendaten (Name, E-Mail, Adresse, etc.)
- `vehicle.*`: Fahrzeugdaten (Marke, Modell, Typ, etc.)
- `appointment.*`: Termindaten (Datum, Uhrzeit)
- `contact.*`: Auftragsdetails (Paket, Wünsche, Budget)

---

## 3. Benutzer und Benutzerrollen

### 3.1 Öffentliche Benutzer

**Eigenschaften:**
- Anonyme Website-Besucher
- Keine Authentifizierung erforderlich
- Schreibzugriff begrenzt auf Kontaktformular
- Lesezugriff auf Informationsseiten

**Funktionen:**
- Website-Browsing
- Kontaktanfrage einreichen
- Verfügbarkeitsprüfung durchführen

### 3.2 Admin-Benutzer

**Eigenschaften:**
- Employees/Betriebspersonal
- Authentifizierung per Passwort (`config/verwaltung_auth.php`)
- Full-Access zur Verwaltungsoberfläche
- Sitzungsbasiert mit Inaktivitäts-Timeout

**Funktionen:**
- Alle Verwaltungsfunktionen (siehe 2.2)
- Kundenbearbeitung
- Auftrag- und Terminverwaltung
- Konfiguration und Stammdatenmanagement
- Statistiken und Reporting (optional)

### 3.3 System-Benutzer

**Eigenschaften:**
- Automatisierte Prozesse
- E-Mail-Versand-System
- Zeitbasierte Aufgaben (z.B. Rate Limiting)

**Funktionen:**
- E-Mail-Versand
- Automatische Benachrichtigungen

---

## 4. Funktionale Anforderungen

### 4.1 Kundenverwaltung

| Anforderung | Beschreibung |
|---|---|
| **F-KD-001** | System muss Kundendaten erfassen: Vorname, Nachname, E-Mail, Telefon, Straße, PLZ, Stadt |
| **F-KD-002** | Jeder Kunde erhält eine eindeutige ID |
| **F-KD-003** | E-Mail-Adresse muss eindeutig sein (UNIQUE-Constraint) |
| **F-KD-004** | Änderungszeitstempel (updated_at) wird automatisch gepflegt |
| **F-KD-005** | Löschung eines Kunden führt zu Löschung aller verknüpften Fahrzeuge und Aufträge (CASCADE) |

### 4.2 Fahrzeugverwaltung

| Anforderung | Beschreibung |
|---|---|
| **F-FB-001** | Fahrzeuge sind an einen Kunden gebunden |
| **F-FB-002** | Pro Fahrzeug können erfasst werden: Marke, Modell, Typ, Kennzeichen, Notizen |
| **F-FB-003** | Fahrzeugtyp muss aus konfigurierbarer Liste stammen |
| **F-FB-004** | Löschung eines Fahrzeugs kann eingeschränkt sein, falls aktive Aufträge bestehen |

### 4.3 Auftragsmanagement

| Anforderung | Beschreibung |
|---|---|
| **F-AU-001** | Jeder Auftrag ist verknüpft mit einem Kunden und Fahrzeug |
| **F-AU-002** | Auftrag enthält: Reinigungspaket, Spezialwünsche, bevorzugtes Datum/Zeit, Status |
| **F-AU-003** | Status-Verlauf: new → contacted → scheduled → completed/cancelled |
| **F-AU-004** | Automatische Preisberechnung basierend auf Paket + Fahrzeugtyp |
| **F-AU-005** | Audit-Trail über created_at und updated_at |

### 4.4 Terminmanagement

| Anforderung | Beschreibung |
|---|---|
| **F-TM-001** | Terminslots sind granular (Datum + Uhrzeit) definierbar |
| **F-TM-002** | Slots können manuell blockiert/freigegeben werden |
| **F-TM-003** | Verfügbarkeitsprüfung berücksichtigt Workload (Zeit pro Slot) |
| **F-TM-004** | Kalenderansicht für Tages-/Wochenübersicht |

### 4.5 Konfiguration und Stammdaten

| Anforderung | Beschreibung |
|---|---|
| **F-KF-001** | Admin kann Reinigungspakete anlegen/ändern/deaktivieren |
| **F-KF-002** | Admin kann Fahrzeugtypen definieren |
| **F-KF-003** | Workload-Referenzen (Zeit + Preis) je Paket/Fahrzeugtyp |
| **F-KF-004** | E-Mail-Templates verwaltbar mit Platzhaltern |
| **F-KF-005** | Sortierreihenfolge konfigurierbar (sort_order) |

### 4.6 Sicherheit und Datenschutz

| Anforderung | Beschreibung |
|---|---|
| **F-SI-001** | Admin-Login mit Passwortschutz |
| **F-SI-002** | CSRF-Token-Prüfung für sensitive POST-Requests |
| **F-SI-003** | Session-Timeout nach Inaktivität (600 Sek.) |
| **F-SI-004** | HTML-Escaping bei Ausgabe (XSS-Schutz) |
| **F-SI-005** | Bot-Protection in öffentlichen Formularen |
| **F-SI-006** | Passwörter mindestens mit `password_hash()` verschlüsselt |
| **F-SI-007** | HTTPS-Zwang für produktive Umgebung |

---

## 5. Datenbankmodell

### 5.1 Entity-Relationship-Diagram (Konzeptionell)

```
┌──────────────┐
│   customer   │
│──────────────│
│ id (PK)      │
│ first_name   │
│ last_name    │
│ email (U)    │
│ phone        │
│ street_addr. │
│ postal_code  │
│ city         │
│ created_at   │
│ updated_at   │
└──────────────┘
       │ (1)
       │ fk
       │
       └─ (n) ──→ ┌────────────────────┐
                  │ customer_vehicle   │
                  │────────────────────│
                  │ id (PK)            │
                  │ customer_id (FK)   │
                  │ brand              │
                  │ model              │
                  │ vehicle_type (FK)  │
                  │ license_plate      │
                  │ note               │
                  │ created_at         │
                  │ updated_at         │
                  └────────────────────┘
                         │ (1)
                         │ fk
                         │
       ┌─────────────────┘
       │
       └──→ ┌───────────────────────┐
            │ vehicle_type_option   │
            │───────────────────────│
            │ type_name (PK)        │
            │ sort_order            │
            │ is_active             │
            │ created_at            │
            │ updated_at            │
            └───────────────────────┘

┌────────────────────────┐
│   customer_request     │
│────────────────────────│
│ id (PK)                │
│ customer_id (FK)       │
│ customer_vehicle_id(FK)│
│ cleaning_package (FK)  │
│ special_wishes         │
│ preferred_date         │
│ preferred_time         │
│ status (ENUM)          │
│ created_at             │
│ updated_at             │
└────────────────────────┘
       │           │
       │           └──→ ┌──────────────────┐
       │                │cleaning_package  │
       │                │──────────────────│
       │                │ package_name (PK)│
       │                │ sort_order       │
       │                │ is_active        │
       │                │ created_at       │
       │                │ updated_at       │
       │                └──────────────────┘
       │                       │ (1)
       │                       │ fk
       │                       │
       └──→ ┌──────────────────────────┐
            │ workload_reference       │
            │──────────────────────────│
            │ id (PK)                  │
            │ cleaning_package (FK)    │
            │ vehicle_type (FK)        │
            │ time_effort              │
            │ net_price                │
            │ created_at               │
            │ updated_at               │
            └──────────────────────────┘

┌──────────────────────────┐
│ schedule_blocked_slot    │
│──────────────────────────│
│ slot_date (PK)           │
│ slot_time (PK)           │
│ created_at               │
└──────────────────────────┘

┌──────────────────────────┐
│  email_template          │
│──────────────────────────│
│ id (PK)                  │
│ template_key (U)         │
│ template_name            │
│ subject_template         │
│ body_template            │
│ is_active                │
│ created_at               │
│ updated_at               │
└──────────────────────────┘
       │ (1)
       │ fk
       │
       └──→ ┌──────────────────────────────┐
            │email_type_template_assignment│
            │──────────────────────────────│
            │ email_type_key (PK)          │
            │ template_id (FK)             │
            │ created_at                   │
            │ updated_at                   │
            └──────────────────────────────┘
```

### 5.2 Tabellendetails

#### **customer** – Kundenstammdaten

| Spalte | Typ | Constraint | Beschreibung |
|---|---|---|---|
| id | INT UNSIGNED | PK, AI | Eindeutige Kunden-ID |
| first_name | VARCHAR(120) | NOT NULL | Vorname |
| last_name | VARCHAR(120) | NOT NULL | Nachname |
| email | VARCHAR(190) | NOT NULL, UNIQUE | E-Mail (eindeutig) |
| phone | VARCHAR(50) | NOT NULL, DEFAULT '' | Telefonnummer |
| street_address | VARCHAR(190) | NOT NULL, DEFAULT '' | Straße und Hausnummer |
| postal_code | VARCHAR(20) | NOT NULL, DEFAULT '' | Postleitzahl |
| city | VARCHAR(120) | NOT NULL, DEFAULT '' | Stadt |
| created_at | TIMESTAMP | NOT NULL, DEFAULT NOW | Erstellungszeitstempel |
| updated_at | TIMESTAMP | NOT NULL, DEFAULT NOW, ON UPDATE | Änderungszeitstempel |

**Indizes:**
- UNIQUE KEY `uq_customer_email` auf (email)

---

#### **customer_vehicle** – Fahrzeuge eines Kunden

| Spalte | Typ | Constraint | Beschreibung |
|---|---|---|---|
| id | INT UNSIGNED | PK, AI | Eindeutige Fahrzeug-ID |
| customer_id | INT UNSIGNED | FK → customer(id) | Zuordnung zum Kunden |
| brand | VARCHAR(120) | NOT NULL | Fahrzeugmarke (z.B. "BMW") |
| model | VARCHAR(120) | NOT NULL | Fahrzeugmodell (z.B. "3er") |
| vehicle_type | VARCHAR(120) | FK → vehicle_type_option(type_name) | Klassifizierung |
| license_plate | VARCHAR(30) | NOT NULL, DEFAULT '' | Kennzeichen |
| note | TEXT | NULL | Notizen (z.B. Besonderheiten) |
| created_at | TIMESTAMP | NOT NULL, DEFAULT NOW | Erstellungszeitstempel |
| updated_at | TIMESTAMP | NOT NULL, DEFAULT NOW, ON UPDATE | Änderungszeitstempel |

**Indizes:**
- KEY `idx_customer_vehicle_customer_id` auf (customer_id)
- KEY `idx_customer_vehicle_customer_vehicle` auf (customer_id, id)
- KEY `idx_customer_vehicle_vehicle_type` auf (vehicle_type)

**Foreign Keys:**
- `fk_customer_vehicle_customer`: ON DELETE CASCADE, ON UPDATE CASCADE
- `fk_customer_vehicle_vehicle_type_option`: ON DELETE SET NULL, ON UPDATE CASCADE

---

#### **cleaning_package** – Reinigungspakete

| Spalte | Typ | Constraint | Beschreibung |
|---|---|---|---|
| id | INT UNSIGNED | PK, AI | Paket-ID |
| package_name | VARCHAR(120) | NOT NULL, UNIQUE | Name des Pakets (z.B. "Basis-Innenreinigung") |
| sort_order | INT | NOT NULL, DEFAULT 0 | Sortierre|henfolge in UI |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | Aktivität (1=aktiv, 0=inaktiv) |
| created_at | TIMESTAMP | NOT NULL, DEFAULT NOW | Erstellungszeitstempel |
| updated_at | TIMESTAMP | NOT NULL, DEFAULT NOW, ON UPDATE | Änderungszeitstempel |

---

#### **vehicle_type_option** – Fahrzeugtyp-Klassifizierungen

| Spalte | Typ | Constraint | Beschreibung |
|---|---|---|---|
| id | INT UNSIGNED | PK, AI | Typ-ID |
| type_name | VARCHAR(120) | NOT NULL, UNIQUE | Name des Typs (z.B. "Kleinwagen") |
| sort_order | INT | NOT NULL, DEFAULT 0 | Sortierreihenfolge |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | Aktivität |
| created_at | TIMESTAMP | NOT NULL, DEFAULT NOW | Erstellungszeitstempel |
| updated_at | TIMESTAMP | NOT NULL, DEFAULT NOW, ON UPDATE | Änderungszeitstempel |

---

#### **customer_request** – Reinigungsaufträge

| Spalte | Typ | Constraint | Beschreibung |
|---|---|---|---|
| id | INT UNSIGNED | PK, AI | Auftrags-ID |
| customer_id | INT UNSIGNED | FK → customer(id) | Zuordnung zum Kunden |
| customer_vehicle_id | INT UNSIGNED | FK → customer_vehicle(id) | Zuordnung zum Fahrzeug |
| cleaning_package | VARCHAR(120) | FK → cleaning_package(package_name) | Reinigungspaket |
| special_wishes | TEXT | NULL | Spezielle Wünsche (z.B. Innenreinigung extra) |
| preferred_date | DATE | NULL | Bevorzugtes Datum |
| preferred_time | VARCHAR(50) | NOT NULL, DEFAULT '' | Bevorzugte Uhrzeit |
| status | ENUM('new','contacted','scheduled','completed','cancelled') | NOT NULL, DEFAULT 'new' | Auftrags-Status |
| created_at | TIMESTAMP | NOT NULL, DEFAULT NOW | Erstellungszeitstempel |
| updated_at | TIMESTAMP | NOT NULL, DEFAULT NOW, ON UPDATE | Änderungszeitstempel |

**Indizes:**
- KEY `idx_customer_request_customer_id` auf (customer_id)
- KEY `idx_customer_request_vehicle_id` auf (customer_vehicle_id)
- KEY `idx_customer_request_cleaning_package` auf (cleaning_package)

**Foreign Keys:**
- Mehrere CASCADE/RESTRICT-Constraints für referenzielle Integrität

---

#### **workload_reference** – Zeit- und Preisreferenzen

| Spalte | Typ | Constraint | Beschreibung |
|---|---|---|---|
| id | INT UNSIGNED | PK, AI | Referenz-ID |
| cleaning_package | VARCHAR(120) | FK → cleaning_package(package_name) | Reinigungspaket |
| vehicle_type | VARCHAR(120) | FK → vehicle_type_option(type_name) | Fahrzeugtyp |
| time_effort | DECIMAL(8,2) | NOT NULL | Zeitaufwand in Stunden (z.B. 2.50) |
| net_price | DECIMAL(10,2) | NOT NULL | Netto-Preis in EUR |
| created_at | TIMESTAMP | NOT NULL, DEFAULT NOW | Erstellungszeitstempel |
| updated_at | TIMESTAMP | NOT NULL, DEFAULT NOW, ON UPDATE | Änderungszeitstempel |

**Unique Constraint:**
- UNIQUE KEY `uq_workload_reference_cleaning_vehicle` auf (cleaning_package, vehicle_type)

---

#### **schedule_blocked_slot** – Blockierte Zeitslots

| Spalte | Typ | Constraint | Beschreibung |
|---|---|---|---|
| slot_date | DATE | PK | Blockiertes Datum |
| slot_time | TIME | PK | Blockierte Uhrzeit |
| created_at | TIMESTAMP | NOT NULL, DEFAULT NOW | Erstellungszeitstempel |

**Verwendung:** Manuelles Blockieren von Zeiten, die nicht buchbar sein sollen.

---

#### **email_template** – E-Mail-Vorlagen

| Spalte | Typ | Constraint | Beschreibung |
|---|---|---|---|
| id | INT UNSIGNED | PK, AI | Template-ID |
| template_key | VARCHAR(120) | NULL, UNIQUE | Interner Schlüssel (z.B. "contact_req_ack") |
| template_name | VARCHAR(190) | NOT NULL | Lesbarer Name |
| subject_template | VARCHAR(255) | NOT NULL | Betreffzeile mit Platzhaltern |
| body_template | TEXT | NOT NULL | E-Mail-Body mit Platzhaltern |
| is_active | TINYINT(1) | NOT NULL, DEFAULT 1 | Aktivität |
| created_at | TIMESTAMP | NOT NULL, DEFAULT NOW | Erstellungszeitstempel |
| updated_at | TIMESTAMP | NOT NULL, DEFAULT NOW, ON UPDATE | Änderungszeitstempel |

---

#### **email_type_template_assignment** – Zuordnung Template-Typen

| Spalte | Typ | Constraint | Beschreibung |
|---|---|---|---|
| email_type_key | VARCHAR(120) | PK | Typ (z.B. "contact_request_notification") |
| template_id | INT UNSIGNED | FK → email_template(id) | Zugeordnetes Template |
| created_at | TIMESTAMP | NOT NULL, DEFAULT NOW | Erstellungszeitstempel |
| updated_at | TIMESTAMP | NOT NULL, DEFAULT NOW, ON UPDATE | Änderungszeitstempel |

---

### 5.3 Datenbankmigrationen

Die Datenbank wird in mehreren Schritten aufgebaut:

| Migration | Inhalt |
|---|---|
| `schema.sql` | Basis-Tabellen (customers, customer, contact_requests, vehicle_type_option, customer_vehicle, cleaning_package, customer_request, schedule_blocked_slot, workload_reference, email_template) |
| `20260315_add_*` | Nachträgliche Spalten und Foreign Keys |
| `20260316_add_*` | E-Mail-Templates und weitere Erweiterungen |

---

## 6. Geschäftsprozesse

### 6.1 Kundenakquisition über Website

```
1. Besucher öffnet Startseite (index.php)
   ↓
2. Besucher navigiert zu Kontakt / Preise
   ↓
3. Besucher füllt Kontaktformular aus:
   - Name, E-Mail, Telefon, Fahrzeugdaten, Paket-Wunsch
   ↓
4. Bot-Protection validiert (FormBotProtection.php)
   ↓
5. Daten werden in contact_requests oder customer_request gespeichert
   ↓
6. E-Mail-Benachrichtigung an Admin (TYPE_CONTACT_REQUEST_NOTIFICATION)
   ↓
7. Kundenbewirtschaftung durch Admin folgt (4.2)
```

### 6.2 Kundenverwaltung im Admin-Bereich

```
1. Admin meldet sich an (verwaltung.php)
   - Authentifizierung per Passwort
   ↓
2. Admin navigiert zu Kundenliste
   ↓
3. Admin führt CRUD-Operationen durch:
   [CREATE] Kunde anlegen (customer_create.php)
   [READ]   Kundendetails ansehen (customer_detail.php)
   [UPDATE] Kundendaten ändern (customer_edit.php)
   [DELETE] Kunde löschen (customer_delete.php)
   ↓
4. Für jeden Kunden können Fahrzeuge verwaltet werden (vehicle_detail.php)
   ↓
5. Session bleibt erhalten, bis Logout oder Timeout (600 Sek. Inaktivität)
```

### 6.3 Auftragsverwaltung und Terminplanung

```
1. Neue Anfrage erfasst / Agent liest Website-Anfrage
   ↓
2. Admin öffnet request_detail.php
   - Kunde wird manuell zugeordnet (oder bereits vorhanden)
   - Fahrzeug wird ausgewählt
   - Reinigungspaket wird definiert
   - Spezialwünsche werden notiert
   ↓
3. Automatische Preisberechnung via workload_reference:
   - Preis = workload_reference(package + vehicle_type).net_price
   ↓
4. Status wird auf "contacted" gesetzt
   ↓
5. Terminplanung:
   - Admin öffnet calendar.php
   - Wählt verfügbaren Slot (blockierte Slots ausgeblendet)
   - Termin wird in appointment_detail.php erfasst
   - Status wird auf "scheduled" gesetzt
   ↓
6. Durchführung und Abschluss:
   - Reinigung wird durchgeführt
   - Admin setzt Status auf "completed"
   - Optional: Bestätigung via E-Mail versenden (TYPE_CONTACT_REQUEST_ACKNOWLEDGEMENT)
```

### 6.4 Konfigurationsmanagement

```
Admin pflegt Stammdaten:

1. Reinigungspakete (cleaning_package)
   - "Basis-Innenreinigung"
   - "Intensiv-Innenreinigung"
   - "Tiefenreinigung"
   - "Textil-Versiegelung"
   ↓
2. Fahrzeugtypen (vehicle_type_option)
   - "Kleinwagen"
   - "Kompaktklasse"
   - "Mittelklasse"
   - "Oberklasse"
   ↓
3. Workload-Referenzen (workload_reference)
   - Zeitaufwand je Paket/Fahrzeugtyp (z.B. 2.5 Stunden)
   - Netto-Preis je Paket/Fahrzeugtyp (z.B. 79,99 EUR)
   ↓
4. E-Mail-Templates (email_template)
   - Betreffzeilen mit Platzhaltern
   - Body mit unterstütztem HTML oder Plain-Text
   ↓
5. Sperrigung von Terminen (schedule_blocked_slot)
   - Manuelle Blockierung nicht verfügbarer Zeiten
```

---

## 7. Sicherheit und Datenschutz

### 7.1 Authentifizierung und Autorisierung

#### Admin-Authentifizierung
- **Passwort-Hash**: Gelagert in `config/verwaltung_auth.php` als `password_hash()`
- **Verifizierung**: `password_verify($eingabe, $hash)` bei Login
- **Session**: `$_SESSION['admin_verified']` = true nach erfolgreichem Login

#### Session-Handling
- **Timeout**: 600 Sekunden Inaktivität → Force-Logout
- **CSRF-Token**: Generiert per `session_start()` und validiert in POST-Handler
- **Ort**: Verwaltet in `public/verwaltung.php`

### 7.2 Schutz vor Angriffsszenarien

| Angriffsszenario | Abwehrmaßnahme |
|---|---|
| **SQL-Injection** | Prepared Statements via PDO (parameterisierte Queries) |
| **XSS (Cross-Site Scripting)** | HTML-Escaping: `e($value)` = `htmlspecialchars(..., ENT_QUOTES)` |
| **CSRF (Cross-Site Request Forgery)** | CSRF-Token in Hidden-Form-Field, Verifizierung in POST-Handler |
| **Bot-Spam** | FormBotProtection.php: Honey Pot, Timing-Checks, User-Agent-Verifizierung |
| **Brute-Force-Login** | Optional: Rate-Limiting in tmp/ (nicht standardmäßig konfiguriert) |
| **Session-Hijacking** | HTTPS-Zwang (produktiv), Secure- und HttpOnly-Flags |

### 7.3 Datenschutz (DSGVO)

Die Anwendung verarbeitet personenbezogene Daten:

#### Verarbeitete Daten
- Kundendaten: Name, E-Mail, Telefon, Adresse, Fahrzeugdaten
- Eingaben: Kontaktanfragen, Spezialwünsche
- Metadaten: IP-Adresse (über Webserver-Log), Zugriffszeitstempel

#### Datenschutzmaßnahmen
1. **Datenschutz-Seite**: `public/datenschutz.php` erklärt die Verarbeitung
2. **Einwilligung**: Checkbox "Ich akzeptiere die Datenschutz" in Kontaktformula
3. **Löschrecht**: Admin kann Kundendaten und zugeordnete Aufträge löschen
4. **Aufbrauchfrist**: Verarbeitete Daten sollten nach Abschluss Reinigung gelöscht werden (Geschäftsmitteilung)
5. **Backup**: Datenbankbackups sollten separat verschlüsselt und begrenzt gelagert sein

#### Rechtskonformität
- **AGB**: `public/agb.php` regelt Vertraglichkeit
- **Widerrufsbelehrung**: `public/widerrufsbelehrung.html` für Verbraucher (14 Tage)
- **Impressum**: `public/impressum.php` mit Kontaktinfos

---

## 8. Benutzerschnittstellen

### 8.1 Öffentliche Website-Nutzerpfade

#### Startseite (index.php)
- **Layout**: Responsiv, Mobile-First
- **Inhalte**: Hero-Section, Leistungsübersicht (mit Bildern), Preisblick, Kontaktform
- **CTA**: "Jetzt Anfrage stellen" → Kontaktformular

#### Einzelseiten
- **Preise** (preise.php): Detaillierte Paketübersicht
- **Kontakt** (kontakt.php): Kontaktformular + Karte
- **Rechtliches**: AGB, Datenschutz, Impressum, Widerrufsbelehrung

#### Formular-Ablauf (index.php / kontakt.php)
```
┌─────────────────────┐
│  Kontaktformular    │
├─────────────────────┤
│ Name, E-Mail, Tel   │
│ Fahrzeug + Paket    │
│ Spezialwünsche      │
│ Einwilligunge       │
└─────────────────────┘
         ↓
    [Bot-Prüfung]
         ↓
[E-Mail-Versand] → Admin
    [Speichern] → contact_requests/customer_request
         ↓
  [Danke-Seite]
```

### 8.2 Admin-Verwaltungsoberfläche (verwaltung.php)

#### Login-Seite
- Einfaches Passwort-Eingabefeld
- Post-Verarbeitung mit Verifizierung
- Fehlerausgabe bei falsches Passwort

#### Dashboard / Navigationsmenü
Nach Login verfügber:
- **Kundenliste** (customers.php)
- **Neue Anfrage** (request_detail.php?action=create)
- **Kalender** (calendar.php)
- **Konfiguration**:
  - Reinigungspakete
  - Fahrzeugtypen
  - Workload-Referenzen
  - E-Mail-Templates
- **Logout**

#### Kundenverwaltung
- **Kundenliste** (customers.php): Suchbar, Pagination
- **Kunde hinzufügen** (customer_create.php): Formular
- **Kunde bearbeiten** (customer_edit.php): Formular mit Pre-filled
- **Kunde löschen** (customer_delete.php): Bestätigung
- **Kundendetails** (customer_detail.php): Fahrzeuge + Anfragen anzeigen

#### Fahrzeugverwaltung
- **Fahrzeug hinzufügen** (vehicle_detail.php?action=new): Formular
- **Fahrzeug bearbeiten** (vehicle_detail.php?id=123): Inline-Formular
- **Fahrzeug-Typ-Auswahl**: Dropdown aus vehicle_type_option

#### Auftragsmanagement
- **Anfrage erstellen** (request_detail.php?action=new): Kunde → Fahrzeug → Paket
- **Anfrage ansehen** (request_detail.php?id=123): Details + Status
- **Anfrage ändern**: Status-Update, Paketänderung
- **Preis anzeigen**: Automatisch basiert auf Paket + Fahrzeugtyp

#### Terminkalender
- **Kalender** (calendar.php): Tages-/Wochenansicht
- **Slot-Blockierung**: Admin kann Zeiten sperren
- **Termin-Ansicht**: Welche Anfragen mit welcher Uhrzeit gebucht sind
- **appointment_detail.php**: Details zur Terminbuchung

### 8.3 Responsive Design

- **CSS-Framework**: Eigenentwickelt (assets/styles.css + spezifische Seiten-CSS)
- **Breakpoints**: Mobile, Tablet, Desktop
- **Bilder**: AVIF-Format mit Fallback
- **Header/Footer**: Partials (site_header.php, site_footer.php)

---

## 9. Integration und Konfiguration

### 9.1 Umgebungskonfiguration

```
config/
├── env.php              # ENV-Variablen-Loader
├── database.php         # PDO-Datenbankverbindung
├── verwaltung_auth.php  # Admin-Passwort (hardcoded oder aus ENV)
└── db.php               # Alternativ: DB-Konfigurationslogik
```

#### Umgebungsvariablen (.env)

```
DB_HOST=mariadb
DB_PORT=3306
DB_NAME=innersense
DB_USER=innersense_user
DB_PASS=innersense_pass
ADMIN_PASS_HASH=<password_hash(..., PASSWORD_BCRYPT)>
SMTP_HOST=mail.example.com
SMTP_PORT=587
SMTP_USER=noreply@innersense-example.de
SMTP_PASS=xxxxx
```

#### Datenbankverbindung (database.php)

```php
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$db   = getenv('DB_NAME') ?: 'innersense';
$user = getenv('DB_USER') ?: 'innersense_user';
$pass = getenv('DB_PASS') ?: 'innersense_pass';

$pdo = new PDO(
    "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

### 9.2 PHP-Abhängigkeiten

- **PHP 8.3+**: Verwendete moderne Sprachfeatures (typed properties, match)
- **PDO-Extension**: Für Datenbankzugriff
- **SPL / Standard Library**: Für Basis-Funktionalität
- **Keine externen Libraries**: Klassisches prozedurales PHP (ggf. in Zukunft Composer hinzufügbar)

### 9.3 E-Mail-Integration

#### SMTP-Konfiguration
- Über Umgebungsvariablen definiert
- PHPMailer oder ähnliche Library empfohlen (aktuell unkonfiguriert)

#### Template-Rendering
```php
$template = $emailService->getTemplate('contact_request_notification');
$subject = $emailService->renderTemplate($template['subject_template'], $placeholders);
$body    = $emailService->renderTemplate($template['body_template'], $placeholders);
```

---

## 10. Betrieb und Wartung

### 10.1 Deployment

#### FTP-basiertes Deployment (Scripts)

Die Datei `scripts/deploy.sh` unterstützt automatisiertes Deployment:

```bash
# Hilfsskripte:
- deploy.sh        # Hauptscript
- deploy_settings.py # Python-Konfiguration
- deploy_changed_files.py # Automatische Diff-Erkennung
```

#### Deployment-Workflow
1. Lokale Entwicklung durchführen
2. `deploy.sh` ausführen → geänderte Dateien detektieren
3. Nur geänderte Dateien per FTP hochladen
4. Remote-Datenbankmigrationen ausführen (optional)

#### Migration Server-Credentials
- `mariadb_migration/server_credentials.conf`: SSH/FTP-Zugänge
- Sollte aus VCS ausgenommen sein (.gitignore)

### 10.2 Datenbankwartung

#### Backups
```bash
# Vollständiges Backup
mysqldump -h mariadb -u innersense_user -p innersense > backup_YYYY-MM-DD.sql

# Mit Docker Compose:
docker compose exec -T db mysqldump -u innersense_user -ppass innersense > backup.sql
```

#### Migration neuer Schemas
```bash
# Manuell einzelne Migrationen einfahren:
mariadb -u innersense_user -p innersense < sql/migrations/20260315_*.sql
```

#### Index- und Constraint-Überprüfung
```sql
-- Konsistenz prüfen
ANALYZE TABLE customer, customer_vehicle, customer_request;

-- Größe kontrollieren
SELECT TABLE_NAME, ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS MB
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'innersense';
```

### 10.3 Monitoring und Logging

#### Webserver-Logging
- **Access-Log**: `/var/log/apache2/access.log` (enthält HTTP-Requests)
- **Error-Log**: `/var/log/apache2/error.log` (PHP-Fehler, Exceptions)

#### Applikations-Logging
- Aktuell nicht zentral vorhanden
- Empfehlung: PSR-3 Logger (Monolog) implementieren für:
  - Alle genannten Fehler
  - Auftragsverfolgung
  - Sicherheitsereignisse (Login-Fehlversuche, CSRF-Token-Verletzungen)

#### Zu überwachende Metriken
- Datenbankperformance (Verbindungszahl, Abfragezeit)
- Speichernutzung (tmp/-Verzeichnis für Rate-Limiting-State)
- Fehlerquoten in Error-Logs
- Admin-Login-Erfolgsquote

### 10.4 Wartungsaufgaben

| Task | Frequenz | Owner | Beschreibung |
|---|---|---|---|
| Datenbankbackup | Täglich | DevOps | Vollständiger Dump für Desaster-Recovery |
| Log-Rotation | Wöchentlich | Sysadmin | Apache/PHP Error-Logs komprimieren |
| Sicherheits-Updates | Monatlich | DevOps | PHP/MariaDB-Versionen prüfen, Patches einspielen |
| Performance-Analyse | Monatlich | Dev | Langsame Queries identifizieren und optimieren |
| Archivierung alte Aufträge | Quartalsweise | Business | completed/cancelled Aufträge exportieren und löschen |
| Passwort-Änderung Admin | Halbjährlich | Admin | Admin-Passwort-Hash in verwaltung_auth.php updaten |

### 10.5 Häufige Fehlerbehandlung

#### Problem: "CSRF-Token ungültig"
- **Ursache**: Session abgelaufen oder manipuliert
- **Lösung**: Logout + neues Login durchführen

#### Problem: "Datenbankverbindung fehlgeschlagen"
- **Ursache**: Credentials falsch oder DB offline
- **Lösung**: config/database.php überprüfen, DB-Status prüfen (docker compose logs db)

#### Problem: "E-Mail nicht versendet"
- **Ursache**: SMTP-Config falsch oder E-Mail-Template deaktiviert
- **Lösung**: SMTP-Config (env.php) überprüfen, email_template.is_active = 1 setzen

#### Problem: "Session-Timeout bei inaktiven Tabs"
- **Ursache**: 600 Sekunden Inaktivität
- **Lösung**: Web-App neu laden oder Inaktivitätsgrenzwert erhöhen

### 10.6 Skalierung und Performance

#### Optimierungsmöglichkeiten
1. **Datenbankindizes**: Zusätzliche Indizes für Häufig-Queries (z.B. status in customer_request)
2. **Caching**: Redis-Layer für häufig abgerufene Daten (cleaning_package, vehicle_type_option)
3. **Pagination**: Kundenlisten auf z.B. 50 Einträge pro Seite begrenzen
4. **Assets-Komprimierung**: GZIP-Encoding, CSS/JS-Minifizierung
5. **Datenbank-Replikation**: Read-Replicas für Reporting/Analytics

#### Lasttest-Szenarios
- 100 gleichzeitige Website-Besucher
- 50 simultane Admin-Sessions
- 1000 Kontaktanfragen pro Tag

---

## 11. Glossar

| Begriff | Beschreibung |
|---|---|
| **Cleaning Package** | Vordefinierte Reinigungsangebote (z.B. "Basis", "Intensiv") |
| **Customer** | Registrierter Kunde mit Grunddaten |
| **Customer Request** | Anfrage/Auftrag für eine Reinigung |
| **Customer Vehicle** | Fahrzeug eines Kunden |
| **Slot** | Zeitpunkt (Datum + Uhrzeit) in der Terminplanung |
| **Workload** | Zeitaufwand und Netto-Preis für ein Paket/Fahrzeug-Paar |
| **CSRF-Token** | Cross-Site-Request-Forgery-Schutz mittels Session-Token |
| **PDO** | PHP Database Objects – standardisiertes Datenbank-Interface |
| **Email Template** | Vordefinierte E-Mail mit Platzhaltern |
| **Bot Protection** | Honey-Pot oder Timing-basierte Spam-Erkennung |

---

## 12. Quellenverzeichnis und weiterführende Dokumente

- [Technische Dokumentation](docs/technische_dokumentation.md) – technische Architektur und Deployment
- [VSC KI Voraussetzungen](docs/vsc_ki_voraussetzungen.md) – Entwickler-Setup
- [SQL Schema](sql/schema.sql) – Datenbankstruktur
- [Docker Compose Setup](docker-compose.yml) – Ausführungsumgebung
- [Deployment Skripte](scripts/) – Automatisiertes Deployment

---

**Dokumentversion Ende**

*Diese Dokumentation wird regelmäßig aktualisiert und sollte bei neuen Features oder architekturellen Änderungen fortgeschrieben werden.*

