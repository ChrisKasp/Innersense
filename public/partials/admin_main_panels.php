        <section class="panel" data-view-panel="dashboard">
            <div class="cards-grid">
                <article class="stat-card">
                    <h2>Offene Anfragen</h2>
                    <p class="value"><?= e((string) $dashboardOpenRequests) ?></p>
                    <small><?= e((string) $dashboardOpenRequestsSinceYesterday) ?> seit gestern</small>
                </article>
                <article class="stat-card">
                    <h2>Geplante Termine</h2>
                    <p class="value"><?= e((string) $dashboardPlannedAppointments) ?></p>
                    <small><?= e((string) $dashboardPlannedAppointmentsThisWeek) ?> in dieser Woche</small>
                </article>
                <article class="stat-card">
                    <h2>Aktive Kunden</h2>
                    <p class="value"><?= e((string) $dashboardCustomerCount) ?></p>
                    <small><?= e((string) $dashboardNewCustomersThisMonth) ?> neu in diesem Monat</small>
                </article>
                <article class="stat-card">
                    <h2>Fahrzeuge in Datenbank</h2>
                    <p class="value"><?= e((string) $dashboardVehicleCount) ?></p>
                    <small>inkl. Mehrfahrzeug-Kunden</small>
                </article>
                <article class="stat-card">
                    <h2>Seiten-Aufrufstatistik</h2>
                    <p><a class="primary-link" href="/usage/index.html" target="_blank" rel="noopener">Statistik öffnen</a></p>
                </article>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="requests">
            <div class="panel-actions panel-actions-with-search" data-list-search-toolbar="requests">
                <input
                    type="search"
                    class="list-search-input"
                    placeholder="Anfragen durchsuchen..."
                    data-list-search-input="requests"
                    data-list-search-target="requests-table-body"
                    data-list-search-endpoint="verwaltung.php?ajax=list_search&amp;panel=requests"
                >
                <div class="panel-actions-right">
                    <a class="primary-link" href="request_detail.php">+ Neu</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Datum</th>
                        <th>Kunde</th>
                        <th>Paket</th>
                        <th>Wunschdatum</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody id="requests-table-body"><?php renderRequestsTableBody($requestRows, $requestLoadError, '', $csrfToken); ?></tbody>
                </table>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="customers">
            <div class="panel-actions panel-actions-with-search" data-list-search-toolbar="customers">
                <input
                    type="search"
                    class="list-search-input"
                    placeholder="Kunden durchsuchen..."
                    data-list-search-input="customers"
                    data-list-search-target="customers-table-body"
                    data-list-search-endpoint="verwaltung.php?ajax=list_search&amp;panel=customers"
                >
                <div class="panel-actions-right">
                    <a class="primary-link" href="customer_create.php">+ Neu</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Kunde</th>
                        <th>Typ</th>
                        <th>Firma</th>
                        <th>E-Mail</th>
                        <th>Telefon</th>
                        <th>Adresse</th>
                        <th>Fahrzeuge</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody id="customers-table-body"><?php renderCustomersTableBody($customerRows, $customerLoadError, ''); ?></tbody>
                </table>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="vehicles">
            <div class="panel-actions panel-actions-with-search" data-list-search-toolbar="vehicles">
                <input
                    type="search"
                    class="list-search-input"
                    placeholder="Fahrzeuge durchsuchen..."
                    data-list-search-input="vehicles"
                    data-list-search-target="vehicles-table-body"
                    data-list-search-endpoint="verwaltung.php?ajax=list_search&amp;panel=vehicles"
                >
                <div class="panel-actions-right">
                    <a class="primary-link" href="vehicle_detail.php">+ Neu</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Kunde</th>
                        <th>Marke</th>
                        <th>Modell</th>
                        <th>Typ</th>
                        <th>Kennzeichen</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody id="vehicles-table-body"><?php renderVehiclesTableBody($vehicleRows, $vehicleLoadError, ''); ?></tbody>
                </table>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="workload">
            <div class="panel-actions panel-actions-with-search" data-list-search-toolbar="workload">
                <input
                    type="search"
                    class="list-search-input"
                    placeholder="Aufwände durchsuchen..."
                    data-list-search-input="workload"
                    data-list-search-target="workload-table-body"
                    data-list-search-endpoint="verwaltung.php?ajax=list_search&amp;panel=workload"
                >
                <div class="panel-actions-right">
                    <a class="primary-link" href="workload_reference_detail.php?create=1">+ Neu</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Reinigungspaket</th>
                        <th>Fahrzeugtyp</th>
                        <th>Zeitaufwand</th>
                        <th>Nettopreis</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody id="workload-table-body"><?php renderWorkloadTableBody($workloadReferenceRows, ''); ?></tbody>
                </table>
            </div>
        </section>

        <section class="panel is-hidden" data-view-panel="appointments">
            <div class="panel-actions panel-actions-with-search" data-list-search-toolbar="appointments">
                <input
                    type="search"
                    class="list-search-input"
                    placeholder="Termine durchsuchen..."
                    data-list-search-input="appointments"
                    data-list-search-target="appointments-table-body"
                    data-list-search-endpoint="verwaltung.php?ajax=list_search&amp;panel=appointments"
                >
                <div class="panel-actions-right">
                    <a class="primary-link ical-download-link" href="verwaltung.php?view=appointments&amp;download=appointments_ical">iCal Download</a>
                    <a class="primary-link" href="appointment_detail.php?create=1">+ Neu</a>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Termin</th>
                        <th>Kunde</th>
                        <th>Fahrzeug</th>
                        <th>Paket</th>
                        <th>Zeitaufwand</th>
                        <th>Status</th>
                        <th>Aktionen</th>
                    </tr>
                    </thead>
                    <tbody id="appointments-table-body"><?php renderAppointmentsTableBody($appointmentRows, $appointmentLoadError, ''); ?></tbody>
                </table>
            </div>
        </section>
