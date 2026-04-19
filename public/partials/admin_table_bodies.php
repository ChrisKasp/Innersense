<?php

declare(strict_types=1);

function normalizeSearchValue(string $value): string
{
    return mb_strtolower(trim($value), 'UTF-8');
}

/**
 * @param list<string> $haystacks
 */
function rowMatchesSearch(array $haystacks, string $query): bool
{
    $needle = normalizeSearchValue($query);
    if ($needle === '') {
        return true;
    }

    $joined = normalizeSearchValue(implode(' ', $haystacks));
    return str_contains($joined, $needle);
}

/**
 * @param list<array<string, mixed>> $rows
 */
function renderRequestsTableBody(array $rows, string $loadError, string $query, string $csrfToken): void
{
    if ($loadError !== '') {
        echo '<tr><td colspan="6">' . e($loadError) . '</td></tr>';
        return;
    }

    $matchedRows = [];
    foreach ($rows as $request) {
        $customerName = trim(((string) ($request['first_name'] ?? '')) . ' ' . ((string) ($request['last_name'] ?? '')));
        $createdAtRaw = (string) ($request['created_at'] ?? '');
        $preferredDateRaw = (string) ($request['preferred_date'] ?? '');
        $createdAt = $createdAtRaw !== '' ? date('d.m.Y', strtotime($createdAtRaw)) : '-';
        $preferredDate = $preferredDateRaw !== '' ? date('d.m.Y', strtotime($preferredDateRaw)) : '-';
        $status = strtolower(trim((string) ($request['status'] ?? 'new')));
        $statusLabelMap = [
            'new' => 'neu',
            'contacted' => 'kontaktiert',
            'scheduled' => 'geplant',
            'completed' => 'abgeschlossen',
            'cancelled' => 'storniert',
        ];
        $statusLabel = $statusLabelMap[$status] ?? ($status !== '' ? $status : 'neu');
        $package = (string) (($request['cleaning_package'] ?? '') !== '' ? $request['cleaning_package'] : '-');

        if (!rowMatchesSearch([$createdAt, $customerName, $package, $preferredDate, $statusLabel], $query)) {
            continue;
        }

        $matchedRows[] = $request;
    }

    if ($matchedRows === []) {
        $message = trim($query) === '' ? 'Noch keine Anfragen vorhanden.' : 'Keine passenden Anfragen gefunden.';
        echo '<tr><td colspan="6">' . e($message) . '</td></tr>';
        return;
    }

    foreach ($matchedRows as $request) {
        $customerName = trim(((string) ($request['first_name'] ?? '')) . ' ' . ((string) ($request['last_name'] ?? '')));
        $createdAtRaw = (string) ($request['created_at'] ?? '');
        $preferredDateRaw = (string) ($request['preferred_date'] ?? '');
        $createdAt = $createdAtRaw !== '' ? date('d.m.Y', strtotime($createdAtRaw)) : '-';
        $preferredDate = $preferredDateRaw !== '' ? date('d.m.Y', strtotime($preferredDateRaw)) : '-';
        $status = strtolower(trim((string) ($request['status'] ?? 'new')));

        $statusLabelMap = [
            'new' => 'neu',
            'contacted' => 'kontaktiert',
            'scheduled' => 'geplant',
            'completed' => 'abgeschlossen',
            'cancelled' => 'storniert',
        ];
        $statusClassMap = [
            'new' => 'warning',
            'contacted' => 'ok',
            'scheduled' => 'neutral',
            'completed' => 'ok',
            'cancelled' => 'neutral',
        ];
        $statusLabel = $statusLabelMap[$status] ?? ($status !== '' ? $status : 'neu');
        $statusClass = $statusClassMap[$status] ?? 'neutral';

        echo '<tr>';
        echo '<td>' . e($createdAt) . '</td>';
        echo '<td>' . e($customerName !== '' ? $customerName : 'Unbekannt') . '</td>';
        echo '<td>' . e((string) (($request['cleaning_package'] ?? '') !== '' ? $request['cleaning_package'] : '-')) . '</td>';
        echo '<td>' . e($preferredDate) . '</td>';
        echo '<td><span class="badge ' . e($statusClass) . '">' . e($statusLabel) . '</span></td>';
        $requestId = (int) ($request['id'] ?? 0);
        $isClosable = !in_array($status, ['completed', 'cancelled'], true);

        echo '<td><div class="row-actions">';
        echo '<a class="action-icon" href="request_detail.php?id=' . e((string) $requestId) . '" aria-label="Anfrage im Detail anzeigen" title="Details anzeigen">&#128269;</a>';
        if ($isClosable && $requestId > 0) {
            echo '<form method="post" action="verwaltung.php?view=requests" class="action-inline-form" onsubmit="return confirm(\'Anfrage direkt als abgeschlossen markieren?\');">';
            echo '<input type="hidden" name="auth_action" value="complete_request">';
            echo '<input type="hidden" name="csrf_token" value="' . e($csrfToken) . '">';
            echo '<input type="hidden" name="request_id" value="' . e((string) $requestId) . '">';
            echo '<button class="action-icon action-icon-complete" type="submit" aria-label="Anfrage abschliessen" title="Anfrage abschliessen">&#10003;</button>';
            echo '</form>';
        }
        echo '</div></td>';
        echo '</tr>';
    }
}

/**
 * @param list<array<string, mixed>> $rows
 */
function renderCustomersTableBody(array $rows, string $loadError, string $query): void
{
    if ($loadError !== '') {
        echo '<tr><td colspan="8">' . e($loadError) . '</td></tr>';
        return;
    }

    $matchedRows = [];
    foreach ($rows as $customer) {
        $fullName = trim(((string) ($customer['first_name'] ?? '')) . ' ' . ((string) ($customer['last_name'] ?? '')));
        $streetAddress = trim((string) ($customer['street_address'] ?? ''));
        $postalCode = trim((string) ($customer['postal_code'] ?? ''));
        $city = trim((string) ($customer['city'] ?? ''));
        $customerType = trim((string) ($customer['customer_typ'] ?? ''));
        $companyName = trim((string) ($customer['company_name'] ?? ''));
        $addressParts = array_values(array_filter([$streetAddress, trim($postalCode . ' ' . $city)]));
        $address = $addressParts === [] ? '-' : implode(', ', $addressParts);

        if (!rowMatchesSearch([$fullName, $customerType, $companyName, (string) ($customer['email'] ?? ''), (string) ($customer['phone'] ?? ''), $address], $query)) {
            continue;
        }

        $matchedRows[] = $customer;
    }

    if ($matchedRows === []) {
        $message = trim($query) === '' ? 'Noch keine Kunden vorhanden.' : 'Keine passenden Kunden gefunden.';
        echo '<tr><td colspan="8">' . e($message) . '</td></tr>';
        return;
    }

    foreach ($matchedRows as $customer) {
        $fullName = trim(((string) ($customer['first_name'] ?? '')) . ' ' . ((string) ($customer['last_name'] ?? '')));
        $streetAddress = trim((string) ($customer['street_address'] ?? ''));
        $postalCode = trim((string) ($customer['postal_code'] ?? ''));
        $city = trim((string) ($customer['city'] ?? ''));
        $customerType = trim((string) ($customer['customer_typ'] ?? ''));
        $companyName = trim((string) ($customer['company_name'] ?? ''));
        $addressParts = array_values(array_filter([$streetAddress, trim($postalCode . ' ' . $city)]));
        $address = $addressParts === [] ? '-' : implode(', ', $addressParts);

        echo '<tr>';
        echo '<td>' . e($fullName !== '' ? $fullName : 'Unbekannt') . '</td>';
        echo '<td>' . e($customerType !== '' ? $customerType : '-') . '</td>';
        echo '<td>' . e($companyName !== '' ? $companyName : '-') . '</td>';
        echo '<td>' . e((string) ($customer['email'] ?? '-')) . '</td>';
        echo '<td>' . e((string) (($customer['phone'] ?? '') !== '' ? $customer['phone'] : '-')) . '</td>';
        echo '<td>' . e($address) . '</td>';
        echo '<td>' . e((string) ($customer['vehicle_count'] ?? '0')) . '</td>';
        echo '<td><a class="action-icon" href="customer_detail.php?id=' . e((string) ($customer['id'] ?? 0)) . '" aria-label="Kunde im Detail anzeigen" title="Details anzeigen">&#128269;</a></td>';
        echo '</tr>';
    }
}

/**
 * @param list<array<string, mixed>> $rows
 */
function renderVehiclesTableBody(array $rows, string $loadError, string $query): void
{
    if ($loadError !== '') {
        echo '<tr><td colspan="6">' . e($loadError) . '</td></tr>';
        return;
    }

    $matchedRows = [];
    foreach ($rows as $vehicle) {
        $vehicleOwner = trim(((string) ($vehicle['first_name'] ?? '')) . ' ' . ((string) ($vehicle['last_name'] ?? '')));
        $vehicleType = trim((string) ($vehicle['vehicle_type'] ?? ''));
        $licensePlate = trim((string) ($vehicle['license_plate'] ?? ''));
        $brand = (string) ($vehicle['brand'] ?? '');
        $model = (string) ($vehicle['model'] ?? '');

        if (!rowMatchesSearch([$vehicleOwner, $brand, $model, $vehicleType, $licensePlate], $query)) {
            continue;
        }

        $matchedRows[] = $vehicle;
    }

    if ($matchedRows === []) {
        $message = trim($query) === '' ? 'Noch keine Fahrzeuge vorhanden.' : 'Keine passenden Fahrzeuge gefunden.';
        echo '<tr><td colspan="6">' . e($message) . '</td></tr>';
        return;
    }

    foreach ($matchedRows as $vehicle) {
        $vehicleOwner = trim(((string) ($vehicle['first_name'] ?? '')) . ' ' . ((string) ($vehicle['last_name'] ?? '')));
        $vehicleType = trim((string) ($vehicle['vehicle_type'] ?? ''));
        $licensePlate = trim((string) ($vehicle['license_plate'] ?? ''));

        echo '<tr>';
        echo '<td>' . e($vehicleOwner !== '' ? $vehicleOwner : 'Unbekannt') . '</td>';
        echo '<td>' . e((string) (($vehicle['brand'] ?? '') !== '' ? $vehicle['brand'] : '-')) . '</td>';
        echo '<td>' . e((string) (($vehicle['model'] ?? '') !== '' ? $vehicle['model'] : '-')) . '</td>';
        echo '<td>' . e($vehicleType !== '' ? $vehicleType : '-') . '</td>';
        echo '<td>' . e($licensePlate !== '' ? $licensePlate : '-') . '</td>';
        echo '<td><a class="action-icon" href="vehicle_detail.php?id=' . e((string) ($vehicle['id'] ?? 0)) . '" aria-label="Fahrzeug im Detail anzeigen" title="Details anzeigen">&#128269;</a></td>';
        echo '</tr>';
    }
}

/**
 * @param list<array<string, mixed>> $rows
 */
function renderWorkloadTableBody(array $rows, string $query): void
{
    $matchedRows = [];
    foreach ($rows as $workloadReference) {
        $packageName = trim((string) ($workloadReference['cleaning_package'] ?? ''));
        $vehicleType = trim((string) ($workloadReference['vehicle_type'] ?? ''));
        $timeEffortValue = (float) ($workloadReference['time_effort'] ?? 0);
        $netPriceValue = (float) ($workloadReference['net_price'] ?? 0);
        $timeLabel = number_format($timeEffortValue, 2, ',', '.') . ' h';
        $priceLabel = number_format($netPriceValue, 2, ',', '.') . ' EUR';

        if (!rowMatchesSearch([$packageName, $vehicleType, $timeLabel, $priceLabel], $query)) {
            continue;
        }

        $matchedRows[] = $workloadReference;
    }

    if ($matchedRows === []) {
        $message = trim($query) === '' ? 'Noch keine Arbeits- und Kosten-Aufwände vorhanden.' : 'Keine passenden Arbeits- und Kosten-Aufwände gefunden.';
        echo '<tr><td colspan="5">' . e($message) . '</td></tr>';
        return;
    }

    foreach ($matchedRows as $workloadReference) {
        $referenceId = (int) ($workloadReference['id'] ?? 0);
        $packageName = trim((string) ($workloadReference['cleaning_package'] ?? ''));
        $vehicleType = trim((string) ($workloadReference['vehicle_type'] ?? ''));
        $timeEffortValue = (float) ($workloadReference['time_effort'] ?? 0);
        $netPriceValue = (float) ($workloadReference['net_price'] ?? 0);

        echo '<tr>';
        echo '<td>' . e($packageName !== '' ? $packageName : '-') . '</td>';
        echo '<td>' . e($vehicleType !== '' ? $vehicleType : '-') . '</td>';
        echo '<td>' . e(number_format($timeEffortValue, 2, ',', '.')) . ' h</td>';
        echo '<td>' . e(number_format($netPriceValue, 2, ',', '.')) . ' EUR</td>';
        echo '<td><a class="action-icon" href="workload_reference_detail.php?id=' . e((string) $referenceId) . '" aria-label="Aufwandseintrag im Detail anzeigen" title="Details anzeigen">&#128269;</a></td>';
        echo '</tr>';
    }
}

/**
 * @param list<array<string, mixed>> $rows
 */
function renderAppointmentsTableBody(array $rows, string $loadError, string $query): void
{
    if ($loadError !== '') {
        echo '<tr><td colspan="7">' . e($loadError) . '</td></tr>';
        return;
    }

    $matchedRows = [];
    foreach ($rows as $appointment) {
        $customerName = trim(((string) ($appointment['first_name'] ?? '')) . ' ' . ((string) ($appointment['last_name'] ?? '')));
        $vehicleLabel = trim(((string) ($appointment['brand'] ?? '')) . ' ' . ((string) ($appointment['model'] ?? '')));
        $vehicleType = trim((string) ($appointment['vehicle_type'] ?? ''));
        $preferredDateRaw = trim((string) ($appointment['preferred_date'] ?? ''));
        $preferredTimeRaw = trim((string) ($appointment['preferred_time'] ?? ''));
        $timeEffortRaw = $appointment['time_effort'] ?? null;

        $datePart = $preferredDateRaw !== '' ? date('d.m.Y', strtotime($preferredDateRaw)) : '-';
        $appointmentDateTime = $datePart;
        if ($preferredTimeRaw !== '' && $datePart !== '-') {
            $appointmentDateTime .= ' ' . $preferredTimeRaw;
        }

        $timeEffortLabel = '-';
        if ($timeEffortRaw !== null && $timeEffortRaw !== '') {
            $timeEffortLabel = number_format((float) $timeEffortRaw, 2, ',', '.') . ' h';
        }

        $status = strtolower(trim((string) ($appointment['status'] ?? 'new')));
        $statusLabelMap = [
            'new' => 'neu',
            'contacted' => 'kontaktiert',
            'scheduled' => 'geplant',
            'completed' => 'abgeschlossen',
            'cancelled' => 'storniert',
        ];
        $statusLabel = $statusLabelMap[$status] ?? ($status !== '' ? $status : 'neu');
        $package = (string) (($appointment['cleaning_package'] ?? '') !== '' ? $appointment['cleaning_package'] : '-');

        if (!rowMatchesSearch([$appointmentDateTime, $customerName, $vehicleLabel, $vehicleType, $package, $timeEffortLabel, $statusLabel], $query)) {
            continue;
        }

        $matchedRows[] = $appointment;
    }

    if ($matchedRows === []) {
        $message = trim($query) === '' ? 'Noch keine Termine vorhanden.' : 'Keine passenden Termine gefunden.';
        echo '<tr><td colspan="7">' . e($message) . '</td></tr>';
        return;
    }

    foreach ($matchedRows as $appointment) {
        $customerName = trim(((string) ($appointment['first_name'] ?? '')) . ' ' . ((string) ($appointment['last_name'] ?? '')));
        $vehicleLabel = trim(((string) ($appointment['brand'] ?? '')) . ' ' . ((string) ($appointment['model'] ?? '')));
        $vehicleType = trim((string) ($appointment['vehicle_type'] ?? ''));
        $preferredDateRaw = trim((string) ($appointment['preferred_date'] ?? ''));
        $preferredTimeRaw = trim((string) ($appointment['preferred_time'] ?? ''));
        $timeEffortRaw = $appointment['time_effort'] ?? null;

        $datePart = $preferredDateRaw !== '' ? date('d.m.Y', strtotime($preferredDateRaw)) : '-';
        $appointmentDateTime = $datePart;
        if ($preferredTimeRaw !== '' && $datePart !== '-') {
            $appointmentDateTime .= ' ' . $preferredTimeRaw;
        }

        $timeEffortLabel = '-';
        if ($timeEffortRaw !== null && $timeEffortRaw !== '') {
            $timeEffortLabel = number_format((float) $timeEffortRaw, 2, ',', '.') . ' h';
        }

        $status = strtolower(trim((string) ($appointment['status'] ?? 'new')));
        $statusLabelMap = [
            'new' => 'neu',
            'contacted' => 'kontaktiert',
            'scheduled' => 'geplant',
            'completed' => 'abgeschlossen',
            'cancelled' => 'storniert',
        ];
        $statusClassMap = [
            'new' => 'warning',
            'contacted' => 'ok',
            'scheduled' => 'neutral',
            'completed' => 'ok',
            'cancelled' => 'neutral',
        ];
        $statusLabel = $statusLabelMap[$status] ?? ($status !== '' ? $status : 'neu');
        $statusClass = $statusClassMap[$status] ?? 'neutral';

        echo '<tr>';
        echo '<td>' . e($appointmentDateTime) . '</td>';
        echo '<td>' . e($customerName !== '' ? $customerName : 'Unbekannt') . '</td>';
        echo '<td>' . e($vehicleLabel !== '' ? $vehicleLabel . ($vehicleType !== '' ? ' (' . $vehicleType . ')' : '') : '-') . '</td>';
        echo '<td>' . e((string) (($appointment['cleaning_package'] ?? '') !== '' ? $appointment['cleaning_package'] : '-')) . '</td>';
        echo '<td>' . e($timeEffortLabel) . '</td>';
        echo '<td><span class="badge ' . e($statusClass) . '">' . e($statusLabel) . '</span></td>';
        echo '<td><a class="action-icon" href="appointment_detail.php?id=' . e((string) ($appointment['id'] ?? 0)) . '" aria-label="Termin im Detail anzeigen" title="Details anzeigen">&#128269;</a></td>';
        echo '</tr>';
    }
}
