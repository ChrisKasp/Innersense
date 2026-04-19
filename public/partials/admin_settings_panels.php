        <section class="panel is-hidden" data-view-panel="settings_social">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>Social Media & Kontakt</h2>
                    <p>WhatsApp-Nummer, Kontakt-E-Mail, Betriebsadresse sowie Facebook- und Instagram-Links für Website und Kontaktformular bearbeiten.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_social'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_social'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <form method="post" action="verwaltung.php?view=settings_social" class="settings-form">
                        <input type="hidden" name="auth_action" value="save_social_settings">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <label for="whatsapp_number">WhatsApp Nummer</label>
                        <input
                            id="whatsapp_number"
                            type="text"
                            name="whatsapp_number"
                            value="<?= e((string) ($socialMediaSettings['whatsapp_number'] ?? '')) ?>"
                            placeholder="z. B. +491739982284"
                        >

                        <label for="facebook_url">Facebook URL</label>
                        <input
                            id="facebook_url"
                            type="url"
                            name="facebook_url"
                            value="<?= e((string) ($socialMediaSettings['facebook_url'] ?? '')) ?>"
                            placeholder="https://www.facebook.com/..."
                        >

                        <label for="instagram_url">Instagram URL</label>
                        <input
                            id="instagram_url"
                            type="url"
                            name="instagram_url"
                            value="<?= e((string) ($socialMediaSettings['instagram_url'] ?? '')) ?>"
                            placeholder="https://www.instagram.com/..."
                        >

                        <label for="contact_email">Kontakt-E-Mail</label>
                        <input
                            id="contact_email"
                            type="email"
                            name="contact_email"
                            value="<?= e((string) ($socialMediaSettings['contact_email'] ?? '')) ?>"
                            placeholder="kontakt@beispiel.de"
                            required
                        >

                        <label for="business_address">Betriebsadresse</label>
                        <input
                            id="business_address"
                            type="text"
                            name="business_address"
                            placeholder="z. B. Musterstraße 1, 12345 Musterstadt"
                            value="<?= e((string) ($socialMediaSettings['business_address'] ?? '')) ?>"
                        >

                        <button type="submit">Kontaktdaten speichern</button>
                    </form>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="settings_password">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>Passwort Verwaltung</h2>
                    <p>Hier kann das Login-Passwort für den geschützten Verwaltungsbereich geändert werden.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_password'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_password'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <form method="post" action="verwaltung.php?view=settings_password" class="settings-form">
                        <input type="hidden" name="auth_action" value="change_password">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <div class="password-settings-row">
                            <div class="password-settings-item">
                                <label for="current_password">Aktuelles Passwort</label>
                                <div class="password-field-wrap">
                                    <input id="current_password" type="password" name="current_password" autocomplete="current-password" required data-password-field>
                                    <button class="password-eye-btn" type="button" aria-label="Passwort anzeigen" title="Passwort anzeigen/verbergen" data-password-toggle-btn>
                                        <span aria-hidden="true" data-password-toggle-icon>&#128065;&#65039;</span>
                                    </button>
                                </div>
                            </div>

                            <div class="password-settings-item">
                                <label for="new_password">Neues Passwort</label>
                                <div class="password-field-wrap">
                                    <input id="new_password" type="password" name="new_password" minlength="10" autocomplete="new-password" required data-password-field>
                                    <button class="password-eye-btn" type="button" aria-label="Passwort anzeigen" title="Passwort anzeigen/verbergen" data-password-toggle-btn>
                                        <span aria-hidden="true" data-password-toggle-icon>&#128065;&#65039;</span>
                                    </button>
                                </div>
                            </div>

                            <div class="password-settings-item">
                                <label for="confirm_password">Neues Passwort wiederholen</label>
                                <div class="password-field-wrap">
                                    <input id="confirm_password" type="password" name="confirm_password" minlength="10" autocomplete="new-password" required data-password-field>
                                    <button class="password-eye-btn" type="button" aria-label="Passwort anzeigen" title="Passwort anzeigen/verbergen" data-password-toggle-btn>
                                        <span aria-hidden="true" data-password-toggle-icon>&#128065;&#65039;</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <button type="submit">Passwort speichern</button>
                    </form>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="settings_vehicle_types">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>Fahrzeugtypen</h2>
                    <p>Fahrzeugtypen für Formulare und Fahrzeugverwaltung pflegen.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_vehicle_types'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_vehicle_types'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <form method="post" action="verwaltung.php?view=settings_vehicle_types" class="settings-form">
                        <input type="hidden" name="auth_action" value="add_vehicle_type">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <label for="vehicle_type_name">Neuer Fahrzeugtyp</label>
                        <input id="vehicle_type_name" type="text" name="vehicle_type_name" placeholder="z. B. Coupé" required>

                        <button type="submit">Fahrzeugtyp hinzufügen</button>
                    </form>

                    <div class="table-wrap" style="margin-top:0.9rem;">
                        <table>
                            <thead>
                            <tr>
                                <th>Typ</th>
                                <th>Status</th>
                                <th>Aktion</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($vehicleTypeRows === []): ?>
                                <tr>
                                    <td colspan="3">Keine Fahrzeugtypen vorhanden.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($vehicleTypeRows as $vehicleTypeRow): ?>
                                    <?php
                                    $typeName = trim((string) ($vehicleTypeRow['type_name'] ?? ''));
                                    $isActive = (int) ($vehicleTypeRow['is_active'] ?? 1) === 1;
                                    ?>
                                    <tr>
                                        <td><?= e($typeName) ?></td>
                                        <td><?= $isActive ? 'Aktiv' : 'Inaktiv' ?></td>
                                        <td>
                                            <form method="post" action="verwaltung.php?view=settings_vehicle_types" onsubmit="return confirm('Fahrzeugtyp wirklich löschen?');">
                                                <input type="hidden" name="auth_action" value="delete_vehicle_type">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="vehicle_type_name" value="<?= e($typeName) ?>">
                                                <button type="submit" class="danger-button">Löschen</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="settings_cleaning_packages">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>Reinigungspakete</h2>
                    <p>Reinigungspakete für Kontaktformular und Anfrage-/Terminverwaltung pflegen.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_cleaning_packages'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_cleaning_packages'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <form method="post" action="verwaltung.php?view=settings_cleaning_packages" class="settings-form">
                        <input type="hidden" name="auth_action" value="add_cleaning_package">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                        <label for="cleaning_package_name">Neues Reinigungspaket</label>
                        <input id="cleaning_package_name" type="text" name="cleaning_package_name" placeholder="z. B. Premium Aufbereitung" required>

                        <button type="submit">Reinigungspaket hinzufügen</button>
                    </form>

                    <div class="table-wrap" style="margin-top:0.9rem;">
                        <table>
                            <thead>
                            <tr>
                                <th>Paket</th>
                                <th>Status</th>
                                <th>Aktion</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($cleaningPackageRows === []): ?>
                                <tr>
                                    <td colspan="3">Keine Reinigungspakete vorhanden.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($cleaningPackageRows as $cleaningPackageRow): ?>
                                    <?php
                                    $packageName = trim((string) ($cleaningPackageRow['package_name'] ?? ''));
                                    $isActive = (int) ($cleaningPackageRow['is_active'] ?? 1) === 1;
                                    ?>
                                    <tr>
                                        <td><?= e($packageName) ?></td>
                                        <td><?= $isActive ? 'Aktiv' : 'Inaktiv' ?></td>
                                        <td>
                                            <form method="post" action="verwaltung.php?view=settings_cleaning_packages" onsubmit="return confirm('Reinigungspaket wirklich löschen?');">
                                                <input type="hidden" name="auth_action" value="delete_cleaning_package">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="cleaning_package_name" value="<?= e($packageName) ?>">
                                                <button type="submit" class="danger-button">Löschen</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="settings_email_templates">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>E-Mail-Vorlagen</h2>
                    <p>Vorlagen fuer Betreff und Inhalt pflegen. Platzhalter werden beim Versand ersetzt.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_email_templates'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_email_templates'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <form method="post" action="verwaltung.php?view=settings_email_templates" class="settings-form">
                        <input type="hidden" name="auth_action" value="save_email_template">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="template_id" value="0">

                        <label for="new_template_name">Neue Vorlage: Name</label>
                        <input id="new_template_name" type="text" name="template_name" placeholder="z. B. Standard Rueckmeldung" required>

                        <label for="new_subject_template">Neue Vorlage: Betreff</label>
                        <input id="new_subject_template" type="text" name="subject_template" placeholder="z. B. Danke fuer deine Anfrage" required>

                        <label for="new_body_template">Neue Vorlage: Inhalt</label>
                        <textarea id="new_body_template" name="body_template" rows="7" placeholder="Text mit Platzhaltern wie {{customer.first_name}}" required></textarea>

                        <button type="submit">Neue Vorlage speichern</button>
                    </form>

                    <div class="settings-inline-note">
                        Verfuegbare Platzhalter:
                        <?php foreach ($allowedEmailPlaceholders as $placeholder): ?>
                            <code>{{<?= e($placeholder) ?>}}</code>
                        <?php endforeach; ?>
                    </div>

                    <div class="table-wrap" style="margin-top:0.9rem;">
                        <table class="email-template-table">
                            <colgroup>
                                <col style="width:25%">
                                <col style="width:26%">
                                <col style="width:40%">
                                <col style="width:10%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>Name</th>
                                <th>Betreff</th>
                                <th>Inhalt</th>
                                <th>Aktion</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($emailTemplateRows === []): ?>
                                <tr>
                                    <td colspan="4">Keine E-Mail-Vorlagen vorhanden.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($emailTemplateRows as $emailTemplateRow): ?>
                                    <?php
                                    $templateId = max(0, (int) ($emailTemplateRow['id'] ?? 0));
                                    $templateName = trim((string) ($emailTemplateRow['template_name'] ?? ''));
                                    $subjectTemplate = (string) ($emailTemplateRow['subject_template'] ?? '');
                                    $bodyTemplate = (string) ($emailTemplateRow['body_template'] ?? '');
                                    $templateFormId = 'email-template-form-' . $templateId;
                                    ?>
                                    <tr>
                                        <td>
                                            <input
                                                id="template_name_<?= e((string) $templateId) ?>"
                                                class="table-input"
                                                type="text"
                                                name="template_name"
                                                value="<?= e($templateName) ?>"
                                                form="<?= e($templateFormId) ?>"
                                                required
                                            >
                                        </td>
                                        <td>
                                            <input
                                                id="subject_template_<?= e((string) $templateId) ?>"
                                                class="table-input"
                                                type="text"
                                                name="subject_template"
                                                value="<?= e($subjectTemplate) ?>"
                                                form="<?= e($templateFormId) ?>"
                                                required
                                            >
                                        </td>
                                        <td>
                                            <textarea
                                                id="body_template_<?= e((string) $templateId) ?>"
                                                class="table-textarea"
                                                name="body_template"
                                                rows="6"
                                                form="<?= e($templateFormId) ?>"
                                                required
                                            ><?= e($bodyTemplate) ?></textarea>
                                        </td>
                                        <td>
                                            <form id="<?= e($templateFormId) ?>" method="post" action="verwaltung.php?view=settings_email_templates" class="settings-form settings-form-table-action">
                                                <input type="hidden" name="auth_action" value="save_email_template">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="template_id" value="<?= e((string) $templateId) ?>">
                                                <button type="submit">Vorlage aktualisieren</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="table-wrap" style="margin-top:0.9rem;">
                        <table class="email-type-assignment-table">
                            <colgroup>
                                <col style="width:35%">
                                <col style="width:45%">
                                <col style="width:20%">
                            </colgroup>
                            <thead>
                            <tr>
                                <th>E-Mail-Typ</th>
                                <th>Zuordnung</th>
                                <th>Aktion</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($emailTypeLabels as $emailTypeKey => $emailTypeLabel): ?>
                                <?php
                                $selectedTemplateId = max(0, (int) ($emailTypeAssignments[$emailTypeKey] ?? 0));
                                $assignmentFormId = 'assign-email-template-' . md5($emailTypeKey);
                                ?>
                                <tr>
                                    <td>
                                        <?= e($emailTypeLabel) ?>
                                        <small class="muted-inline"><?= e($emailTypeKey) ?></small>
                                    </td>
                                    <td>
                                        <form id="<?= e($assignmentFormId) ?>" method="post" action="verwaltung.php?view=settings_email_templates" class="settings-form settings-form-table-action">
                                            <input type="hidden" name="auth_action" value="assign_email_template">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="email_type_key" value="<?= e($emailTypeKey) ?>">

                                            <select class="table-select" name="template_id" required>
                                                <option value="">Bitte waehlen</option>
                                                <?php foreach ($emailTemplateRows as $emailTemplateRow): ?>
                                                    <?php
                                                    $templateOptionId = max(0, (int) ($emailTemplateRow['id'] ?? 0));
                                                    $templateOptionName = trim((string) ($emailTemplateRow['template_name'] ?? ''));
                                                    ?>
                                                    <option value="<?= e((string) $templateOptionId) ?>" <?= $templateOptionId === $selectedTemplateId ? 'selected' : '' ?>>
                                                        <?= e($templateOptionName !== '' ? $templateOptionName : ('Vorlage #' . $templateOptionId)) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </form>
                                    </td>
                                    <td>
                                        <button type="submit" form="<?= e($assignmentFormId) ?>">Zuordnung speichern</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="settings_workload_reference">
            <div class="settings-grid">
                <article class="password-box settings-single">
                    <h2>Workload Referenz</h2>
                    <p>Die Pflege von Arbeits- und Kosten-Aufwänden erfolgt in der eigenständigen Aufwands-Liste mit Detailansicht.</p>

                    <?php if ($settingsNotice !== '' && $initialView === 'settings_workload_reference'): ?>
                        <p class="form-notice"><?= e($settingsNotice) ?></p>
                    <?php endif; ?>
                    <?php if ($settingsError !== '' && $initialView === 'settings_workload_reference'): ?>
                        <p class="form-error"><?= e($settingsError) ?></p>
                    <?php endif; ?>

                    <div class="panel-actions" style="margin-top:0.85rem;">
                        <a class="primary-link" href="verwaltung.php?view=workload">Aufwands-Liste öffnen</a>
                        <a class="primary-link" href="workload_reference_detail.php?create=1">+ Neu</a>
                    </div>
                </article>
            </div>
        </section>
