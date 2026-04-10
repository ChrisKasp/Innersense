# Voraussetzungen fuer die Arbeit mit VS Code und KI-Unterstuetzung

## 1. Ziel
Diese Uebersicht beschreibt die notwendigen Voraussetzungen, um in Visual Studio Code (VS Code) so zu arbeiten wie im gezeigten Beispiel: mit KI-Chat, Code-Vervollstaendigung und Agent-Funktionen.

## 2. Technische Grundvoraussetzungen

- Installiertes VS Code (aktuelle stabile Version empfohlen)
- Stabile Internetverbindung
- GitHub-Konto
- Installierte VS-Code-Erweiterungen:
  - GitHub Copilot
  - GitHub Copilot Chat

Optional, je nach Projekt:

- Git installiert (Versionsverwaltung)
- Laufzeitumgebung fuer das jeweilige Projekt (z. B. PHP, Python, Node.js, Docker)

## 3. Lizenzen: Was ist zwingend, was optional?

### 3.1 Zwingend fuer Copilot in VS Code
Um die KI-Funktionen in VS Code mit GitHub Copilot zu nutzen, ist eine aktive GitHub-Copilot-Lizenz erforderlich:

- Copilot Individual
- Copilot Business
- Copilot Enterprise

Ohne aktive Copilot-Lizenz sind Chat- und Codevorschlag-Funktionen in dieser Form nicht verfuegbar.

### 3.2 ChatGPT-Lizenz
Eine ChatGPT-Lizenz ist fuer GitHub Copilot in VS Code nicht zwingend notwendig.

ChatGPT ist nur dann relevant, wenn es separat genutzt werden soll, zum Beispiel:

- im Browser (Free, Plus, Team, Enterprise)
- ueber API-Zugriff mit eigener Abrechnung

## 4. Typische Lizenz-Szenarien

### Szenario A: Nur GitHub Copilot in VS Code
- Noetig: GitHub-Konto + aktive Copilot-Lizenz
- Nicht noetig: ChatGPT Plus/Team

### Szenario B: Copilot in VS Code + zusaetzlich ChatGPT im Browser
- Noetig: GitHub-Konto + Copilot-Lizenz
- Optional: zusaetzliche ChatGPT-Lizenz

### Szenario C: Unternehmen mit zentraler Steuerung
- Noetig: Copilot Business oder Enterprise ueber GitHub-Organisation
- Optional: separate Freigabe fuer ChatGPT durch IT-/Compliance-Richtlinien

## 5. Unternehmensanforderungen (haeufig relevant)

- Freigabe durch IT/Compliance
- SSO/Identitaetsmanagement (falls vorgeschrieben)
- Proxy-/Firewall-Freigaben fuer GitHub-Dienste
- Richtlinien fuer Datenverarbeitung, Logging und Telemetrie

## 6. Einrichtung in VS Code (Kurzablauf)

1. VS Code installieren und starten.
2. Mit dem GitHub-Konto anmelden.
3. Erweiterungen GitHub Copilot und GitHub Copilot Chat installieren.
4. Copilot-Lizenz pruefen (Account/Abonnement aktiv).
5. In VS Code einen Chat oeffnen und Testprompt senden.

## 7. Haeufige Probleme und Ursachen

- Problem: Kein KI-Chat oder keine Vervollstaendigung
  - Ursache: Keine aktive Copilot-Lizenz oder nicht angemeldet
- Problem: Funktionen im Firmennetzwerk gehen nicht
  - Ursache: Proxy/Firewall blockiert Endpunkte
- Problem: Unterschiedliche Funktionen zwischen Nutzern
  - Ursache: Unterschiedliche Lizenzstufen oder Organisationsrichtlinien

## 8. Kurzfazit
Fuer das gezeigte Arbeitsmodell in VS Code ist in der Regel folgendes ausreichend:

- VS Code
- GitHub-Konto
- aktive GitHub-Copilot-Lizenz

Eine separate ChatGPT-Lizenz ist nur optional und nur dann erforderlich, wenn ChatGPT zusaetzlich ausserhalb von Copilot genutzt werden soll.
