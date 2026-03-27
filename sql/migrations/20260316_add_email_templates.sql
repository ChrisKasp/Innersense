CREATE TABLE IF NOT EXISTS email_template (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS email_type_template_assignment (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO email_template (template_key, template_name, subject_template, body_template, is_active)
VALUES (
    'default_contact_request_notification',
    'Standard: Meldung Kontakt-Anfrage',
    'Neue Kontakt-Anfrage von {{customer.full_name}}',
    'Neue Kontakt-Anfrage\n\nVorname: {{customer.first_name}}\nNachname: {{customer.last_name}}\nE-Mail: {{customer.email}}\nTelefon: {{customer.phone}}\n\nStrasse + Hausnummer: {{customer.street_address}}\nPLZ: {{customer.postal_code}}\nOrt: {{customer.city}}\nAutomarke: {{vehicle.brand}}\nModell: {{vehicle.model}}\nFahrzeugtyp: {{vehicle.type}}\nKennzeichen: {{vehicle.license_plate}}\nReinigungspaket: {{contact.cleaning_package}}\nWunschdatum: {{appointment.date}}\nWunschzeit: {{appointment.time}}\n\nProdukte/Dienstleistungen: {{contact.products}}\nWas brauchst du?: {{contact.needs}}\nHilfreiche Links: {{contact.helpful_links}}\nVerfuegbares Budget: {{contact.budget}}\n\nBesondere Wuensche:\n{{contact.special_wishes}}\n',
    1
),
(
    'default_contact_request_acknowledgement',
    'Standard: Rueckmeldung Kontakt-Anfrage',
    'Danke fuer deine Anfrage bei Innersense',
    'Hallo {{customer.first_name}},\n\ndanke fuer deine Anfrage. Wir melden uns zeitnah mit einer Rueckmeldung.\n\nDeine Angaben im Ueberblick:\nName: {{customer.full_name}}\nE-Mail: {{customer.email}}\nTelefon: {{customer.phone}}\nFahrzeug: {{vehicle.brand}} {{vehicle.model}} ({{vehicle.type}})\nReinigungspaket: {{contact.cleaning_package}}\nWunschdatum/-zeit: {{appointment.date}} {{appointment.time}}\n\nViele Gruesse\nInnersense\n',
    1
);

INSERT IGNORE INTO email_type_template_assignment (email_type_key, template_id)
SELECT 'contact_request_notification', t.id
FROM email_template t
WHERE t.template_key = 'default_contact_request_notification'
LIMIT 1;

INSERT IGNORE INTO email_type_template_assignment (email_type_key, template_id)
SELECT 'contact_request_acknowledgement', t.id
FROM email_template t
WHERE t.template_key = 'default_contact_request_acknowledgement'
LIMIT 1;
