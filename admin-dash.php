<?php include 'header.php'; ?>

<div class="body">
    <div class="container">
        <ul class="nav nav-tabs" id="myTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="cycle-tab" data-toggle="tab" data-target="#cycle" type="button"
                    role="tab" aria-controls="cycle" aria-selected="true">Cycle</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="audit-tab" data-toggle="tab" data-target="#audit" type="button" role="tab"
                    aria-controls="audit" aria-selected="false">Audit Log</button>
            </li>
        </ul>
        <div class="tab-content" id="myTabContent">
            <div class="tab-pane fade show active" id="cycle" role="tabpanel" aria-labelledby="cycle-tab">
                <div class="cycle-card">
                    <!-- Flash -->
                    <div id="flash" class="alert alert-dismissible fade" role="alert" style="display:none;">
                        <span id="flashText"></span>
                        <button id="flashClose" type="button" class="close" aria-label="Close"><span
                                aria-hidden="true">&times;</span></button>
                    </div>

                    <!-- Settings -->
                    <div class="card shadow-sm mb-4">
                        <div class="card-body">
                            <h5 class="mb-3">Cycle Settings</h5>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label for="startDate">Start Date</label>
                                    <input id="startDate" type="date" class="form-control" autocomplete="off">
                                </div>
                                <div class="form-group col-md-6">
                                    <label for="repeatWeeks">Repeat Rule (weeks)</label>
                                    <input id="repeatWeeks" type="number" min="1" step="1" value="2"
                                        class="form-control">
                                    <small class="small-help">End date = start + (weeks × 7 − 1) days.</small>
                                </div>
                                <div class="form-group col-6 d-flex align-items-end">
                                    <div class="btn-group w-100">
                                        <button id="btnReset" class="btn btn-secondary mr-2">Reset</button>
                                        <button id="btnSave" class="btn btn-primary">Save Cycle</button>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex flex-wrap align-items-center mb-2">
                                <div class="custom-control custom-switch mr-auto mb-2">
                                    <input type="checkbox" class="custom-control-input" id="cycleOpen" checked required>
                                    <label class="custom-control-label" for="cycleOpen"><span id="openLabel">Current
                                            Cycle:
                                            OPEN</span></label>
                                </div>
                            </div>

                            <hr>
                            <div class="d-flex align-items-center">
                                <div class="mr-3 text-muted">Summary:</div>
                                <div id="summary" class="font-weight-bold">—</div>
                            </div>
                        </div>
                    </div>

                    <!-- History -->
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <div class="d-flex align-items-center mb-2">
                                <h5 class="mb-0 mr-auto">Cycle History</h5>
                                <button id="btnClear" class="btn btn-outline-danger btn-sm">Clear History</button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-striped mb-0">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Start</th>
                                            <th>End (auto)</th>
                                            <th>Duration</th>
                                            <th>Weeks</th>
                                            <th>Open?</th>
                                            <th>Saved At</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyBody">
                                        <tr class="empty">
                                            <td colspan="8" class="text-center py-4">No cycles saved yet.</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="tab-pane fade" id="audit" role="tabpanel" aria-labelledby="audit-tab">
                <div class="main-content">
                    <div class="row">
                        <div class="col-lg-8 mt-3 mt-md-1 order-2 order-md-1">
                            <div class="results-section">
                                <h2 class="results-header">Results:</h2>

                                <div class="table-container">
                                    <table id="auditTable">
                                        <thead>
                                            <tr>
                                                <th class="sortable" onclick="sortTable(0)">Date</th>
                                                <th class="sortable" onclick="sortTable(1)">User</th>
                                                <th class="sortable" onclick="sortTable(2)">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tableBody">
                                            <tr>
                                                <td>Aug 4<br>20:45</td>
                                                <td>Admin</td>
                                                <td>Sent</td>
                                            </tr>
                                            <tr>
                                                <td>Aug 3<br>23:22</td>
                                                <td>Ava</td>
                                                <td>Edited</td>
                                            </tr>
                                            <tr>
                                                <td>Aug 1<br>12:56</td>
                                                <td>Ava</td>
                                                <td>Edited</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <button class="btn btn-primary" onclick="exportCSV()">Export CSV</button>
                            </div>
                        </div>

                        <div class="col-lg-4 order-1 order-md-2">
                            <div class="filters-section">
                                <button class="filters-toggle">
                                    <span class="toggleIcon">▼</span> Show Filters
                                </button>

                                <div class="filters-content" id="filtersContent">
                                    <h2 class="filters-header">
                                        <span>▼</span>
                                        Filters:
                                    </h2>

                                    <div class="filter-group">
                                        <label class="filter-label">Date:</label>
                                        <div class="date-inputs">
                                            <label class="filter-label">Start Date:
                                                <input placeholder="Start Date" type="date" style="width: 130px;"
                                                    class="filter-input" id="auditstartDate">
                                            </label>
                                            <label class="filter-label">End Date:
                                                <input placeholder="End Date" type="date" style="width: 130px;"
                                                    class="filter-input" id="auditendDate">
                                            </label>
                                        </div>
                                    </div>

                                    <div class="filter-group">
                                        <label class="filter-label">Users:</label>
                                        <input type="text" class="filter-input" id="userFilter"
                                            placeholder="Enter user name...">
                                    </div>

                                    <div class="filter-group">
                                        <label class="filter-label">Type:</label>
                                        <select class="filter-input" id="typeFilter">
                                            <option value="">Update</option>
                                            <option value="sent">Sent</option>
                                            <option value="edited">Edited</option>
                                            <option value="deleted">Deleted</option>
                                            <option value="created">Created</option>
                                        </select>
                                    </div>

                                    <div class="filter-buttons">
                                        <button class="btn btn-primary" onclick="resetFilters()">Reset</button>
                                        <button class="btn btn-secondary" onclick="applyFilters()">Search</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>