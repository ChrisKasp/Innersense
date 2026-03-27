<?php

declare(strict_types=1);

final class EmailTemplateService
{
    public const TYPE_CONTACT_REQUEST_NOTIFICATION = 'contact_request_notification';
    public const TYPE_CONTACT_REQUEST_ACKNOWLEDGEMENT = 'contact_request_acknowledgement';

    /**
     * @return array<string, string>
     */
    public static function getEmailTypeLabels(): array
    {
        return [
            self::TYPE_CONTACT_REQUEST_NOTIFICATION => 'Meldung einer Kontakt-Anfrage',
            self::TYPE_CONTACT_REQUEST_ACKNOWLEDGEMENT => 'Rueckmeldung auf Kontakt-Anfrage',
        ];
    }

    /**
     * @return list<string>
     */
    public static function getAllowedPlaceholders(): array
    {
        return [
            'customer.first_name',
            'customer.last_name',
            'customer.full_name',
            'customer.email',
            'customer.phone',
            'customer.street_address',
            'customer.postal_code',
            'customer.city',
            'vehicle.brand',
            'vehicle.model',
            'vehicle.type',
            'vehicle.license_plate',
            'appointment.date',
            'appointment.time',
            'contact.cleaning_package',
            'contact.special_wishes',
            'contact.products',
            'contact.needs',
            'contact.helpful_links',
            'contact.budget',
        ];
    }

    public static function ensureTables(PDO $pdo): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS email_template (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_key VARCHAR(120) NULL,
                template_name VARCHAR(190) NOT NULL,
                subject_template VARCHAR(255) NOT NULL,
                body_template TEXT NOT NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_email_template_key (template_key),
                KEY idx_email_template_name (template_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS email_type_template_assignment (
                email_type_key VARCHAR(120) NOT NULL,
                template_id INT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (email_type_key),
                KEY idx_email_type_template_assignment_template_id (template_id),
                CONSTRAINT fk_email_type_template_assignment_template
                    FOREIGN KEY (template_id)
                    REFERENCES email_template (id)
                    ON DELETE RESTRICT
                    ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        self::seedDefaults($pdo);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function getTemplates(PDO $pdo): array
    {
        self::ensureTables($pdo);

        $stmt = $pdo->query(
            'SELECT id, template_name, subject_template, body_template, is_active, updated_at
             FROM email_template
             ORDER BY updated_at DESC, id DESC'
        );

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string, int>
     */
    public static function getTypeAssignments(PDO $pdo): array
    {
        self::ensureTables($pdo);

        $stmt = $pdo->query(
            'SELECT email_type_key, template_id
             FROM email_type_template_assignment'
        );

        $assignments = [];
        foreach ($stmt->fetchAll() as $row) {
            $typeKey = trim((string) ($row['email_type_key'] ?? ''));
            if ($typeKey === '') {
                continue;
            }

            $assignments[$typeKey] = max(0, (int) ($row['template_id'] ?? 0));
        }

        return $assignments;
    }

    public static function saveTemplate(PDO $pdo, int $templateId, string $templateName, string $subjectTemplate, string $bodyTemplate): bool
    {
        self::ensureTables($pdo);

        $templateName = trim($templateName);
        $subjectTemplate = trim($subjectTemplate);
        $bodyTemplate = trim($bodyTemplate);

        if ($templateName === '' || $subjectTemplate === '' || $bodyTemplate === '') {
            return false;
        }

        if ($templateId > 0) {
            $stmt = $pdo->prepare(
                'UPDATE email_template
                 SET template_name = :template_name,
                     subject_template = :subject_template,
                     body_template = :body_template,
                     updated_at = CURRENT_TIMESTAMP
                 WHERE id = :id'
            );

            $stmt->execute([
                ':id' => $templateId,
                ':template_name' => $templateName,
                ':subject_template' => $subjectTemplate,
                ':body_template' => $bodyTemplate,
            ]);

            return true;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO email_template (template_name, subject_template, body_template, is_active)
             VALUES (:template_name, :subject_template, :body_template, 1)'
        );

        return $stmt->execute([
            ':template_name' => $templateName,
            ':subject_template' => $subjectTemplate,
            ':body_template' => $bodyTemplate,
        ]);
    }

    public static function assignTemplateToType(PDO $pdo, string $emailTypeKey, int $templateId): bool
    {
        self::ensureTables($pdo);

        if (!self::isValidEmailTypeKey($emailTypeKey) || $templateId <= 0) {
            return false;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO email_type_template_assignment (email_type_key, template_id)
             VALUES (:email_type_key, :template_id)
             ON DUPLICATE KEY UPDATE template_id = VALUES(template_id)'
        );

        return $stmt->execute([
            ':email_type_key' => $emailTypeKey,
            ':template_id' => $templateId,
        ]);
    }

    /**
     * @param array<string, string|int|float|null> $context
     * @return array{subject:string, body:string}|null
     */
    public static function renderForType(PDO $pdo, string $emailTypeKey, array $context): ?array
    {
        self::ensureTables($pdo);

        if (!self::isValidEmailTypeKey($emailTypeKey)) {
            return null;
        }

        $stmt = $pdo->prepare(
            'SELECT t.subject_template, t.body_template
             FROM email_type_template_assignment a
             INNER JOIN email_template t ON t.id = a.template_id
             WHERE a.email_type_key = :email_type_key
             LIMIT 1'
        );
        $stmt->execute([':email_type_key' => $emailTypeKey]);
        $row = $stmt->fetch();

        if (!is_array($row)) {
            return null;
        }

        $subjectTemplate = (string) ($row['subject_template'] ?? '');
        $bodyTemplate = (string) ($row['body_template'] ?? '');

        $normalizedContext = self::normalizeContext($context);

        return [
            'subject' => self::renderTemplate($subjectTemplate, $normalizedContext),
            'body' => self::renderTemplate($bodyTemplate, $normalizedContext),
        ];
    }

    /**
     * @return list<string>
     */
    public static function findInvalidPlaceholders(string $subjectTemplate, string $bodyTemplate): array
    {
        $allowed = array_fill_keys(self::getAllowedPlaceholders(), true);
        $found = array_merge(self::extractPlaceholders($subjectTemplate), self::extractPlaceholders($bodyTemplate));

        $invalid = [];
        foreach ($found as $placeholder) {
            if (!isset($allowed[$placeholder])) {
                $invalid[$placeholder] = true;
            }
        }

        return array_keys($invalid);
    }

    public static function isValidEmailTypeKey(string $emailTypeKey): bool
    {
        $labels = self::getEmailTypeLabels();
        return isset($labels[$emailTypeKey]);
    }

    private static function seedDefaults(PDO $pdo): void
    {
        $defaultTemplates = [
            [
                'template_key' => 'default_' . self::TYPE_CONTACT_REQUEST_NOTIFICATION,
                'template_name' => 'Standard: Meldung Kontakt-Anfrage',
                'subject_template' => 'Neue Kontakt-Anfrage von {{customer.full_name}}',
                'body_template' => "Neue Kontakt-Anfrage\n\n"
                    . "Vorname: {{customer.first_name}}\n"
                    . "Nachname: {{customer.last_name}}\n"
                    . "E-Mail: {{customer.email}}\n"
                    . "Telefon: {{customer.phone}}\n\n"
                    . "Strasse + Hausnummer: {{customer.street_address}}\n"
                    . "PLZ: {{customer.postal_code}}\n"
                    . "Ort: {{customer.city}}\n"
                    . "Automarke: {{vehicle.brand}}\n"
                    . "Modell: {{vehicle.model}}\n"
                    . "Fahrzeugtyp: {{vehicle.type}}\n"
                    . "Kennzeichen: {{vehicle.license_plate}}\n"
                    . "Reinigungspaket: {{contact.cleaning_package}}\n"
                    . "Wunschdatum: {{appointment.date}}\n"
                    . "Wunschzeit: {{appointment.time}}\n\n"
                    . "Produkte/Dienstleistungen: {{contact.products}}\n"
                    . "Was brauchst du?: {{contact.needs}}\n"
                    . "Hilfreiche Links: {{contact.helpful_links}}\n"
                    . "Verfuegbares Budget: {{contact.budget}}\n\n"
                    . "Besondere Wuensche:\n{{contact.special_wishes}}\n",
            ],
            [
                'template_key' => 'default_' . self::TYPE_CONTACT_REQUEST_ACKNOWLEDGEMENT,
                'template_name' => 'Standard: Rueckmeldung Kontakt-Anfrage',
                'subject_template' => 'Danke fuer deine Anfrage bei Innersense',
                'body_template' => "Hallo {{customer.first_name}},\n\n"
                    . "danke fuer deine Anfrage. Wir melden uns zeitnah mit einer Rueckmeldung.\n\n"
                    . "Deine Angaben im Ueberblick:\n"
                    . "Name: {{customer.full_name}}\n"
                    . "E-Mail: {{customer.email}}\n"
                    . "Telefon: {{customer.phone}}\n"
                    . "Fahrzeug: {{vehicle.brand}} {{vehicle.model}} ({{vehicle.type}})\n"
                    . "Reinigungspaket: {{contact.cleaning_package}}\n"
                    . "Wunschdatum/-zeit: {{appointment.date}} {{appointment.time}}\n\n"
                    . "Viele Gruesse\nInnersense\n",
            ],
        ];

        $insertTemplateStmt = $pdo->prepare(
            'INSERT IGNORE INTO email_template (template_key, template_name, subject_template, body_template, is_active)
             VALUES (:template_key, :template_name, :subject_template, :body_template, 1)'
        );

        foreach ($defaultTemplates as $template) {
            $insertTemplateStmt->execute([
                ':template_key' => $template['template_key'],
                ':template_name' => $template['template_name'],
                ':subject_template' => $template['subject_template'],
                ':body_template' => $template['body_template'],
            ]);
        }

        $assignments = [
            self::TYPE_CONTACT_REQUEST_NOTIFICATION => 'default_' . self::TYPE_CONTACT_REQUEST_NOTIFICATION,
            self::TYPE_CONTACT_REQUEST_ACKNOWLEDGEMENT => 'default_' . self::TYPE_CONTACT_REQUEST_ACKNOWLEDGEMENT,
        ];

        $assignStmt = $pdo->prepare(
            'INSERT IGNORE INTO email_type_template_assignment (email_type_key, template_id)
             SELECT :email_type_key, t.id
             FROM email_template t
             WHERE t.template_key = :template_key
             LIMIT 1'
        );

        foreach ($assignments as $emailTypeKey => $templateKey) {
            $assignStmt->execute([
                ':email_type_key' => $emailTypeKey,
                ':template_key' => $templateKey,
            ]);
        }
    }

    /**
     * @param array<string, string|int|float|null> $context
     * @return array<string, string>
     */
    private static function normalizeContext(array $context): array
    {
        $normalized = [];
        foreach ($context as $key => $value) {
            $stringValue = '-';
            if (is_string($value)) {
                $stringValue = trim($value) !== '' ? $value : '-';
            } elseif (is_int($value) || is_float($value)) {
                $stringValue = (string) $value;
            }

            $normalized[$key] = $stringValue;
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $context
     */
    private static function renderTemplate(string $template, array $context): string
    {
        $allowed = array_fill_keys(self::getAllowedPlaceholders(), true);

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z0-9_.]+)\s*\}\}/i',
            static function (array $matches) use ($context, $allowed): string {
                $placeholder = strtolower((string) ($matches[1] ?? ''));
                if (!isset($allowed[$placeholder])) {
                    return (string) ($matches[0] ?? '');
                }

                return (string) ($context[$placeholder] ?? '-');
            },
            $template
        );
    }

    /**
     * @return list<string>
     */
    private static function extractPlaceholders(string $template): array
    {
        preg_match_all('/\{\{\s*([a-z0-9_.]+)\s*\}\}/i', $template, $matches);
        if (!isset($matches[1]) || !is_array($matches[1])) {
            return [];
        }

        $placeholders = [];
        foreach ($matches[1] as $name) {
            $normalized = strtolower(trim((string) $name));
            if ($normalized !== '') {
                $placeholders[$normalized] = true;
            }
        }

        return array_keys($placeholders);
    }
}
