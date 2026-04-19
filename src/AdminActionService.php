<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $post
 * @return array{
 *   isAuthenticated:bool,
 *   loginError:string,
 *   shouldRegenerateSession:bool,
 *   shouldRedirect:bool
 * }
 */
function handleAdminLoginAction(
    string $requestMethod,
    array $post,
    string $storedPasswordHash,
    bool $isAuthenticated,
    string $loginError
): array {
    if ($requestMethod !== 'POST' || (string) ($post['auth_action'] ?? '') !== 'login') {
        return [
            'isAuthenticated' => $isAuthenticated,
            'loginError' => $loginError,
            'shouldRegenerateSession' => false,
            'shouldRedirect' => false,
        ];
    }

    $inputPassword = (string) ($post['password'] ?? '');
    if (password_verify($inputPassword, $storedPasswordHash)) {
        return [
            'isAuthenticated' => true,
            'loginError' => '',
            'shouldRegenerateSession' => true,
            'shouldRedirect' => true,
        ];
    }

    return [
        'isAuthenticated' => false,
        'loginError' => 'Passwort ist nicht korrekt.',
        'shouldRegenerateSession' => false,
        'shouldRedirect' => false,
    ];
}

/**
 * @param array<string, mixed> $post
 * @param array<string, string> $socialMediaSettings
 * @return array{
 *   initialView:string,
 *   settingsError:string,
 *   settingsNotice:string,
 *   storedPasswordHash:string,
 *   socialMediaSettings:array<string,string>
 * }
 */
function handleAuthenticatedAdminAction(
    PDO $pdo,
    string $requestMethod,
    array $post,
    string $csrfToken,
    string $initialView,
    string $settingsError,
    string $settingsNotice,
    string $storedPasswordHash,
    array $socialMediaSettings,
    string $authConfigFile,
    string $publicContactConfigFile,
    string $envFile
): array {
    if ($requestMethod !== 'POST') {
        return [
            'initialView' => $initialView,
            'settingsError' => $settingsError,
            'settingsNotice' => $settingsNotice,
            'storedPasswordHash' => $storedPasswordHash,
            'socialMediaSettings' => $socialMediaSettings,
        ];
    }

    $action = (string) ($post['auth_action'] ?? '');
    if ($action === '') {
        return [
            'initialView' => $initialView,
            'settingsError' => $settingsError,
            'settingsNotice' => $settingsNotice,
            'storedPasswordHash' => $storedPasswordHash,
            'socialMediaSettings' => $socialMediaSettings,
        ];
    }

    $postedToken = (string) ($post['csrf_token'] ?? '');

    switch ($action) {
        case 'change_password':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
                $initialView = 'settings_password';
                break;
            }

            $currentPassword = (string) ($post['current_password'] ?? '');
            $newPassword = (string) ($post['new_password'] ?? '');
            $confirmPassword = (string) ($post['confirm_password'] ?? '');

            if (!password_verify($currentPassword, $storedPasswordHash)) {
                $settingsError = 'Aktuelles Passwort ist nicht korrekt.';
                $initialView = 'settings_password';
            } elseif (strlen($newPassword) < 10) {
                $settingsError = 'Das neue Passwort muss mindestens 10 Zeichen haben.';
                $initialView = 'settings_password';
            } elseif (!hash_equals($newPassword, $confirmPassword)) {
                $settingsError = 'Neues Passwort und Wiederholung stimmen nicht überein.';
                $initialView = 'settings_password';
            } else {
                $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                if (!saveVerwaltungPasswordHash($authConfigFile, $newHash)) {
                    $settingsError = 'Passwort konnte nicht gespeichert werden. Dateirechte für config/ prüfen.';
                    $initialView = 'settings_password';
                } else {
                    $storedPasswordHash = $newHash;
                    $settingsNotice = 'Passwort wurde erfolgreich aktualisiert.';
                    $initialView = 'settings_password';
                }
            }
            break;

        case 'save_social_settings':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
                $initialView = 'settings_social';
                break;
            }

            $whatsAppNumber = trim((string) ($post['whatsapp_number'] ?? ''));
            $facebookUrl = trim((string) ($post['facebook_url'] ?? ''));
            $instagramUrl = trim((string) ($post['instagram_url'] ?? ''));
            $contactEmail = trim((string) ($post['contact_email'] ?? ''));
            $businessAddress = trim((string) ($post['business_address'] ?? ''));

            if ($facebookUrl !== '' && filter_var($facebookUrl, FILTER_VALIDATE_URL) === false) {
                $settingsError = 'Facebook-URL ist ungültig.';
                $initialView = 'settings_social';
                break;
            }

            if ($instagramUrl !== '' && filter_var($instagramUrl, FILTER_VALIDATE_URL) === false) {
                $settingsError = 'Instagram-URL ist ungültig.';
                $initialView = 'settings_social';
                break;
            }

            if ($contactEmail === '' || filter_var($contactEmail, FILTER_VALIDATE_EMAIL) === false) {
                $settingsError = 'Kontakt-E-Mail ist ungültig.';
                $initialView = 'settings_social';
                break;
            }

            $nextSettings = [
                'whatsapp_number' => $whatsAppNumber,
                'facebook_url' => $facebookUrl,
                'instagram_url' => $instagramUrl,
                'contact_email' => $contactEmail,
                'business_address' => $businessAddress,
            ];

            if (!upsertEnvValues($envFile, [
                'WHATSAPP_NUMBER' => $whatsAppNumber,
                'FACEBOOK_URL' => $facebookUrl,
                'INSTAGRAM_URL' => $instagramUrl,
                'CONTACT_TO_EMAIL' => $contactEmail,
                'BUSINESS_ADDRESS' => $businessAddress,
            ])) {
                $settingsError = 'config/.env konnte nicht aktualisiert werden. Dateirechte für das Projektverzeichnis prüfen.';
                $initialView = 'settings_social';
                break;
            }

            $legacyConfigSaved = saveSocialMediaSettings($publicContactConfigFile, $nextSettings);
            $socialMediaSettings = $nextSettings;
            $settingsNotice = $legacyConfigSaved
                ? 'Social-Media-Einstellungen wurden gespeichert.'
                : 'Kontaktdaten wurden in config/.env gespeichert (config/public_contact.php konnte nicht aktualisiert werden).';
            $initialView = 'settings_social';
            break;

        case 'add_vehicle_type':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
                $initialView = 'settings_vehicle_types';
                break;
            }

            $typeName = trim((string) ($post['vehicle_type_name'] ?? ''));
            if ($typeName === '') {
                $settingsError = 'Bitte einen Fahrzeugtyp eingeben.';
                $initialView = 'settings_vehicle_types';
            } elseif (!addVehicleType($pdo, $typeName)) {
                $settingsError = 'Fahrzeugtyp konnte nicht gespeichert werden (ggf. bereits vorhanden).';
                $initialView = 'settings_vehicle_types';
            } else {
                $settingsNotice = 'Fahrzeugtyp wurde gespeichert.';
                $initialView = 'settings_vehicle_types';
            }
            break;

        case 'delete_vehicle_type':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
                $initialView = 'settings_vehicle_types';
                break;
            }

            $typeName = trim((string) ($post['vehicle_type_name'] ?? ''));
            if ($typeName === '') {
                $settingsError = 'Ungültiger Fahrzeugtyp.';
                $initialView = 'settings_vehicle_types';
            } elseif (!deleteVehicleType($pdo, $typeName, 'customer_vehicle')) {
                $settingsError = 'Fahrzeugtyp konnte nicht gelöscht werden (evtl. in Verwendung).';
                $initialView = 'settings_vehicle_types';
            } else {
                $settingsNotice = 'Fahrzeugtyp wurde gelöscht.';
                $initialView = 'settings_vehicle_types';
            }
            break;

        case 'add_cleaning_package':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
                $initialView = 'settings_cleaning_packages';
                break;
            }

            $packageName = trim((string) ($post['cleaning_package_name'] ?? ''));
            if ($packageName === '') {
                $settingsError = 'Bitte ein Reinigungspaket eingeben.';
                $initialView = 'settings_cleaning_packages';
            } elseif (!addCleaningPackage($pdo, $packageName)) {
                $settingsError = 'Reinigungspaket konnte nicht gespeichert werden (ggf. bereits vorhanden).';
                $initialView = 'settings_cleaning_packages';
            } else {
                $settingsNotice = 'Reinigungspaket wurde gespeichert.';
                $initialView = 'settings_cleaning_packages';
            }
            break;

        case 'delete_cleaning_package':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
                $initialView = 'settings_cleaning_packages';
                break;
            }

            $packageName = trim((string) ($post['cleaning_package_name'] ?? ''));
            if ($packageName === '') {
                $settingsError = 'Ungültiges Reinigungspaket.';
                $initialView = 'settings_cleaning_packages';
            } elseif (!deleteCleaningPackage($pdo, $packageName, 'customer_request')) {
                $settingsError = 'Reinigungspaket konnte nicht gelöscht werden (evtl. in Verwendung).';
                $initialView = 'settings_cleaning_packages';
            } else {
                $settingsNotice = 'Reinigungspaket wurde gelöscht.';
                $initialView = 'settings_cleaning_packages';
            }
            break;

        case 'add_workload_reference':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
                $initialView = 'settings_workload_reference';
                break;
            }

            $cleaningPackage = trim((string) ($post['cleaning_package_name'] ?? ''));
            $vehicleType = trim((string) ($post['vehicle_type_name'] ?? ''));
            $timeEffort = trim((string) ($post['time_effort'] ?? ''));
            $netPrice = trim((string) ($post['net_price'] ?? ''));

            if ($cleaningPackage === '' || $vehicleType === '') {
                $settingsError = 'Bitte Reinigungspaket und Fahrzeugtyp auswählen.';
                $initialView = 'settings_workload_reference';
            } elseif (!is_numeric($timeEffort) || (float) $timeEffort <= 0) {
                $settingsError = 'Bitte einen gültigen Zeitaufwand eingeben.';
                $initialView = 'settings_workload_reference';
            } elseif (!is_numeric($netPrice) || (float) $netPrice < 0) {
                $settingsError = 'Bitte einen gültigen Nettopreis eingeben.';
                $initialView = 'settings_workload_reference';
            } elseif (!addWorkloadReference($pdo, $cleaningPackage, $vehicleType, (float) $timeEffort, (float) $netPrice)) {
                $settingsError = 'Workload-Referenz konnte nicht gespeichert werden.';
                $initialView = 'settings_workload_reference';
            } else {
                $settingsNotice = 'Workload-Referenz wurde gespeichert.';
                $initialView = 'settings_workload_reference';
            }
            break;

        case 'delete_workload_reference':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungültige Anfrage. Bitte Seite neu laden.';
                $initialView = 'settings_workload_reference';
                break;
            }

            $referenceId = max(0, (int) ($post['workload_reference_id'] ?? 0));
            if ($referenceId <= 0) {
                $settingsError = 'Ungültige Workload-Referenz.';
                $initialView = 'settings_workload_reference';
            } elseif (!deleteWorkloadReference($pdo, $referenceId)) {
                $settingsError = 'Workload-Referenz konnte nicht gelöscht werden.';
                $initialView = 'settings_workload_reference';
            } else {
                $settingsNotice = 'Workload-Referenz wurde gelöscht.';
                $initialView = 'settings_workload_reference';
            }
            break;

        case 'save_email_template':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungueltige Anfrage. Bitte Seite neu laden.';
                $initialView = 'settings_email_templates';
                break;
            }

            $templateId = max(0, (int) ($post['template_id'] ?? 0));
            $templateName = trim((string) ($post['template_name'] ?? ''));
            $subjectTemplate = trim((string) ($post['subject_template'] ?? ''));
            $bodyTemplate = trim((string) ($post['body_template'] ?? ''));

            if ($templateName === '' || $subjectTemplate === '' || $bodyTemplate === '') {
                $settingsError = 'Bitte Name, Betreff und Inhalt der E-Mail-Vorlage ausfuellen.';
                $initialView = 'settings_email_templates';
                break;
            }

            $invalidPlaceholders = EmailTemplateService::findInvalidPlaceholders($subjectTemplate, $bodyTemplate);
            if ($invalidPlaceholders !== []) {
                $settingsError = 'Unbekannte Platzhalter: ' . implode(', ', $invalidPlaceholders);
                $initialView = 'settings_email_templates';
            } elseif (!EmailTemplateService::saveTemplate($pdo, $templateId, $templateName, $subjectTemplate, $bodyTemplate)) {
                $settingsError = 'E-Mail-Vorlage konnte nicht gespeichert werden.';
                $initialView = 'settings_email_templates';
            } else {
                $settingsNotice = 'E-Mail-Vorlage wurde gespeichert.';
                $initialView = 'settings_email_templates';
            }
            break;

        case 'assign_email_template':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungueltige Anfrage. Bitte Seite neu laden.';
                $initialView = 'settings_email_templates';
                break;
            }

            $emailTypeKey = trim((string) ($post['email_type_key'] ?? ''));
            $templateId = max(0, (int) ($post['template_id'] ?? 0));

            if (!EmailTemplateService::isValidEmailTypeKey($emailTypeKey)) {
                $settingsError = 'Unbekannter E-Mail-Typ.';
                $initialView = 'settings_email_templates';
            } elseif ($templateId <= 0) {
                $settingsError = 'Bitte eine gueltige E-Mail-Vorlage auswaehlen.';
                $initialView = 'settings_email_templates';
            } elseif (!EmailTemplateService::assignTemplateToType($pdo, $emailTypeKey, $templateId)) {
                $settingsError = 'Zuordnung fuer E-Mail-Typ konnte nicht gespeichert werden.';
                $initialView = 'settings_email_templates';
            } else {
                $settingsNotice = 'Zuordnung fuer E-Mail-Typ wurde gespeichert.';
                $initialView = 'settings_email_templates';
            }
            break;

        case 'complete_request':
            if (!hash_equals($csrfToken, $postedToken)) {
                $settingsError = 'Ungueltige Anfrage. Bitte Seite neu laden.';
                $initialView = 'requests';
                break;
            }

            $requestId = max(0, (int) ($post['request_id'] ?? 0));
            if ($requestId <= 0) {
                $settingsError = 'Ungueltige Anfrageauswahl.';
                $initialView = 'requests';
            } elseif (!completeRequestById($pdo, $requestId)) {
                $settingsError = 'Anfrage konnte nicht abgeschlossen werden.';
                $initialView = 'requests';
            } else {
                $settingsNotice = 'Anfrage wurde als abgeschlossen markiert.';
                $initialView = 'requests';
            }
            break;
    }

    return [
        'initialView' => $initialView,
        'settingsError' => $settingsError,
        'settingsNotice' => $settingsNotice,
        'storedPasswordHash' => $storedPasswordHash,
        'socialMediaSettings' => $socialMediaSettings,
    ];
}
