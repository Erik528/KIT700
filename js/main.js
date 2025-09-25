$(document).ready(function () {
    //Toggle ONLY the filters panel admin page
    jQuery(function ($) {
        var $btn = $('.filters-toggle');
        var $icon = $btn.find('.toggleIcon');
        var $filters = $('#filtersContent');
        var mq = window.matchMedia('(max-width: 991.98px)'); // < lg

        function sync() {
            // Keep filters open on desktop; allow toggle on mobile
            if (!mq.matches) $filters.addClass('is-open');
            $icon.text($filters.hasClass('is-open') ? '▲' : '▼');
            $btn.attr('aria-expanded', $filters.hasClass('is-open'));
        }

        // Initial state: closed on mobile, open on desktop
        if (mq.matches) $filters.removeClass('is-open'); else $filters.addClass('is-open');
        sync();

        // Toggle only the filters
        $btn.on('click', function (e) {
            e.preventDefault();
            // On desktop this will no-op because sync() forces open; on mobile it toggles
            $filters.toggleClass('is-open');
            sync();
        });

        // Also let the "Filters:" header act as a toggle
        $('.filters-header').on('click', function () { $btn.trigger('click'); });

        // Re-sync when viewport crosses the breakpoint
        (mq.addEventListener ? mq.addEventListener('change', sync) : mq.addListener(sync));
    });
});

$(document).ready(function () {

    //Affirmation form search bar 
    $("#searchInput").on("keyup click", function () {
        let value = $(this).val().toLowerCase();
        let $dropdown = $("#dropdownList");

        if (value.length > 0) {
            $dropdown.addClass("show");
        } else {
            $dropdown.removeClass("show");
        }

        $dropdown.find(".dropdown-item").filter(function () {
            $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
        });
    });

    // Hide dropdown when clicking outside
    $(document).click(function (e) {
        if (!$(e.target).closest('.dropdown').length) {
            $("#dropdownList").removeClass("show");
        }
    });

    //Accordion for Manager Dashboard
    $(function () {
        // Click anywhere on the message header to toggle
        $('.inbox').on('click', '.msg__head', function () {
            const $msg = $(this).closest('.msg');
            const $details = $msg.children('.msg__details');
            const isOpen = $msg.hasClass('is-open');

            // Close any other open items (accordion behavior)
            $msg.siblings('.msg.is-open').each(function () {
                $(this)
                    .removeClass('is-open')
                    .attr('aria-expanded', 'false')
                    .children('.msg__head').attr('aria-expanded', 'false').end()
                    .children('.msg__details').stop(true, true).slideUp(220);
            });

            if (isOpen) {
                // Close current
                $msg.removeClass('is-open').attr('aria-expanded', 'false');
                $(this).attr('aria-expanded', 'false');
                $details.stop(true, true).slideUp(220);
            } else {
                // Open current
                $msg.addClass('is-open').attr('aria-expanded', 'true');
                $(this).attr('aria-expanded', 'true');
                $details.stop(true, true).slideDown(220);
            }
        });
    });

    //Character limit for the snippet in accordion
    $(function () {
        const LIMIT = 10; // words
        $('.snippet').each(function () {
            const $el = $(this);
            const full = $.trim($el.text()).replace(/\s+/g, ' ');
            $el.attr('data-full', full);            // keep original
            const words = full.split(' ');
            if (words.length > LIMIT) {
                const short = words.slice(0, LIMIT).join(' ') + '…';
                $el.text(short).attr('title', full);  // optional: show full on hover
            }
        });
    });

    // Reset selections
    $('#btnReset').on('click', function () {
        $('#reasonsGroup input[type=checkbox]').prop('checked', false);
        $('#reasonsGroup .btn').removeClass('active');
        $('#flagHint').addClass('d-none');
    });

    // Submit -> require selection -> confirm
    $('#btnSubmit').on('click', function () {
        const selected = $('#reasonsGroup input:checkbox:checked').map(function () { return this.value; }).get();
        if (!selected.length) {
            $('#flagHint').removeClass('d-none');
            return;
        }
        $('#flagHint').addClass('d-none');

        // Pass data & move to confirm (hide -> on hidden show)
        $('#confirmModal').data('reasons', selected);
        $('#flagModal').one('hidden.bs.modal', function () { $('#confirmModal').modal('show'); })
            .modal('hide');
    });

    // Confirm No -> back to reasons
    $('#btnNo').on('click', function () {
        $('#confirmModal').one('hidden.bs.modal', function () { $('#flagModal').modal('show'); })
            .modal('hide');
    });

    // Confirm Yes -> show Reported, then (optionally) auto-hide
    $('#btnYes').on('click', function () {
        const reasons = $('#confirmModal').data('reasons') || [];
        console.log('Reported to HR with reasons:', reasons);

        // Clear chips after success
        $('#btnReset').trigger('click');

        $('#confirmModal').one('hidden.bs.modal', function () { $('#reportedModal').modal('show'); })
            .modal('hide');

        // Optional: auto-dismiss success after 2s
        $('#reportedModal').on('shown.bs.modal', function () {
            setTimeout(() => $('#reportedModal').modal('hide'), 2000);
        });
    });

    // Forward button -> info modal
    $('#btnForward').on('click', function () {
        $('#forwardModal').modal('show');
        // Optional auto-dismiss:
        // $('#forwardModal').on('shown.bs.modal', function(){
        //   setTimeout(() => $('#forwardModal').modal('hide'), 2000);
        // });
    });

    //Affirmation word limit
    $(function () {
        $('.affirmation').each(function () {
            const $p = $(this).find('p').first();
            const full = $p.text().trim();
            const words = full.split(/\s+/);

            if (words.length > 20) {
                const short = words.slice(0, 20).join(' ') + '…';
                $p
                    .data('full', full)
                    .data('short', short)
                    .text(short);

                // Add the toggle link
                const $toggle = $('<a href="#" class="load-toggle">Load more</a>');
                $p.after($('<div class="mt-2"></div>').append($toggle));
            }
        });

        // Toggle handler
        $(document).on('click', '.load-toggle', function (e) {
            e.preventDefault();
            const $aff = $(this).closest('.affirmation');
            const $p = $aff.find('p').first();
            const expanded = $(this).data('expanded') === true;

            if (expanded) {
                $p.text($p.data('short'));
                $(this).text('Load more').data('expanded', false);
            } else {
                $p.text($p.data('full'));
                $(this).text('Show less').data('expanded', true);
            }
        });
    });

    //Show only 2 affirmation on startup
    $(function () {
        const $items = $('.affirmation');
        const batch = 2;

        if ($items.length <= batch) return; // nothing to do

        // Hide everything after the first N
        $items.slice(batch).hide();

        // Find a reasonable container to place the button (parent of the first card)
        const $container = $items.first().parent();

        // Inject the button
        const $btn = $('<button type="button" class="btn btn-secondary mt-2 show-more">Show more</button>');
        $container.append($('<div class="text-center my-2"></div>').append($btn));

        // Click handler
        $btn.on('click', function () {
            const hidden = $items.filter(':hidden');

            // If currently collapsed or partially shown, reveal the next batch
            if (hidden.length) {
                hidden.slice(0, batch).slideDown(150);
                if ($items.filter(':hidden').length === 0) {
                    $btn.text('Show less');
                }
            } else {
                // All visible → collapse back to first batch
                $items.slice(batch).slideUp(150);
                $btn.text('Show more');
            }
        });
    });

    //Affirmation sending back button 
    $('#btnBack').on('click', function () {
        $('#returnModal').modal('show');
        $('#returnReason').trigger('input'); // refresh counter/validation
    });

    // Utility: count words (treat any non-empty whitespace-separated token as a word)
    function countWords(str) {
        return str.trim().split(/\s+/).filter(Boolean).length;
    }

    // Live counter + enable/disable Submit
    $('#returnReason').on('input', function () {
        var words = countWords($(this).val());
        $('#wordCounter').text('( ' + words + '/100 )');
        var valid = words > 0 && words <= 100;
        $('#returnSubmit').prop('disabled', !valid);
        $('#returnHint').toggleClass('d-none', valid);
    });

    // Reset
    $('#returnReset').on('click', function () {
        $('#returnReason').val('').trigger('input');
    });

    // Submit (client-side check + placeholder for server call)
    $('#returnSubmit').on('click', function () {
        var text = $('#returnReason').val();
        var words = countWords(text);
        if (words === 0 || words > 100) {
            $('#returnHint').removeClass('d-none');
            return;
        }

        // TODO: send to your server here (example AJAX):
        // $.post('/return-message.php', { reason: text, message_id: 'm1' })
        //   .done(function(){ /* show success / close modal */ })
        //   .fail(function(){ /* show error */ });

        // For now, just close and optionally show a toast/alert
        $('#returnModal').modal('hide');
    });

    // Filter managers as you type
    $('#m1-manager-filter').on('input', function () {
        const q = $(this).val().toLowerCase();
        $('#m1-manager-list .manager-option').each(function () {
            const text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(q) !== -1);
        });
    });

    // Choose a manager
    $('#m1-manager-list').on('click', '.manager-option', function () {
        const name = $(this).find('.font-weight-bold').text().trim();
        const email = $(this).data('email');

        // Optional: visual selection
        $('#m1-manager-list .manager-option').removeClass('active');
        $(this).addClass('active');

        // Show selected pill
        $('#m1-selected-name').text(name + ' (' + email + ')');
        $('#m1-selected-manager').removeClass('d-none');

        // TODO: Persist to backend (example):
        // $.post('/assign-manager.php', { message_id: 'm1', manager_email: email })
        //   .done(() => appendLog('Assigned to ' + name));
        appendLog('Assigned to ' + name); // demo only
    });

    // Helper: append a log line
    function appendLog(text) {
        const now = new Date();
        const stamp = now.toLocaleString('en-AU', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' })
            .replace(',', '');
        $('#m1-log-list').prepend($('<li>').text(stamp + ' — ' + text));
    }

    // Optional: rotate chevron on open/close
    $('#m1-accordion .btn[data-toggle="collapse"]').on('click', function () {
        const icon = $(this).find('i.fa');
        // Wait a tick so collapse can toggle expanded state
        setTimeout(() => {
            const expanded = $(this).attr('aria-expanded') === 'true';
            icon.toggleClass('fa-rotate-180', !expanded);
        }, 200);
    });

    //Admin Cycle Cycle Count JS
    $(function () {
        // Vanilla DOM refs
        const startEl = document.getElementById('startDate');
        const weeksEl = document.getElementById('repeatWeeks');
        const openEl = document.getElementById('cycleOpen');
        const openLbl = document.getElementById('openLabel');
        const summary = document.getElementById('summary');
        const tbody = document.getElementById('historyBody');

        const flashEl = document.getElementById('flash');
        const flashText = document.getElementById('flashText');
        const flashBtn = document.getElementById('flashClose');

        const KEY = 'minjq_admin_cycles';

        // Utils
        const pad = n => String(n).padStart(2, '0');
        const toYMD = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        function parseYMD(s) { if (!s) return null; const [y, m, d] = s.split('-').map(Number); if (!y || !m || !d) return null; return new Date(y, m - 1, d); }
        function addDays(d, n) { const x = new Date(d); x.setDate(x.getDate() + n); return x; }
        function calcEnd(s, w) { return addDays(s, w * 7 - 1); } // inclusive
        function human(d) { return new Intl.DateTimeFormat('en', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' }).format(d); }
        function load() { try { return JSON.parse(localStorage.getItem(KEY) || '[]') } catch { return [] } }
        function save(list) { localStorage.setItem(KEY, JSON.stringify(list)); }

        function flash(msg, type = 'success') {
            flashEl.className = `alert alert-${type} alert-dismissible fade show`;
            flashText.textContent = msg;
            flashEl.style.display = 'block';
            setTimeout(() => { flashEl.classList.remove('show'); setTimeout(() => flashEl.style.display = 'none', 150); }, 2200);
        }
        flashBtn.addEventListener('click', () => { flashEl.classList.remove('show'); setTimeout(() => flashEl.style.display = 'none', 150); });

        function updateSummary() {
            const s = parseYMD(startEl.value);
            const w = Math.max(1, parseInt(weeksEl.value || '1', 10));
            if (!s) { summary.textContent = '—'; return; }
            const e = calcEnd(s, w);
            const days = Math.round((e - s) / 86400000) + 1;
            summary.textContent = `${human(s)} → ${human(e)} • ${days} days (${(days / 7).toFixed(2)} wk) • ${openEl.checked ? 'OPEN' : 'CLOSED'} • every ${w} wk`;
        }

        function render() {
            const list = load();
            tbody.innerHTML = '';
            if (!list.length) {
                tbody.innerHTML = '<tr class="empty"><td colspan="8" class="text-center py-4">No cycles saved yet.</td></tr>';
                return;
            }
            list.forEach((row, i) => {
                const s = parseYMD(row.start), e = parseYMD(row.end);
                const days = Math.round((e - s) / 86400000) + 1;
                const tr = document.createElement('tr');
                tr.innerHTML = `
            <td>${i + 1}</td>
            <td>${human(s)}</td>
            <td>${human(e)}</td>
            <td>${days} days</td>
            <td>${row.weeks}</td>
            <td>${row.open ? 'Yes' : 'No'}</td>
            <td>${new Date(row.savedAt).toLocaleString()}</td>
            <td class="text-right"><button class="btn btn-sm btn-outline-danger" data-id="${row.id}">Delete</button></td>
            `;
                tbody.appendChild(tr);
            });
        }

        // Vanilla handlers
        startEl.addEventListener('change', updateSummary);
        weeksEl.addEventListener('input', () => { if (+weeksEl.value < 1) weeksEl.value = 1; updateSummary(); });
        openEl.addEventListener('change', () => { openLbl.textContent = `Current Cycle: ${openEl.checked ? 'OPEN' : 'CLOSED'}`; updateSummary(); });

        document.getElementById('btnReset').addEventListener('click', () => {
            startEl.value = ''; weeksEl.value = 2; openEl.checked = true; openLbl.textContent = 'Current Cycle: OPEN'; updateSummary();
        });

        document.getElementById('btnSave').addEventListener('click', () => {
            const s = parseYMD(startEl.value); if (!s) return flash('Please select a Start date.', 'warning');
            const w = Math.max(1, parseInt(weeksEl.value || '1', 10));
            const e = calcEnd(s, w);
            const list = load();
            list.unshift({ id: Math.random().toString(36).slice(2), start: toYMD(s), end: toYMD(e), weeks: w, open: !!openEl.checked, savedAt: new Date().toISOString() });
            save(list); render(); flash('Cycle saved.');
        });

        document.getElementById('btnClear').addEventListener('click', () => {
            if (!confirm('Clear all history?')) return;
            localStorage.removeItem(KEY); render(); flash('History cleared.', 'secondary');
        });

        // Minimal jQuery: one delegated handler for delete buttons
        $('#historyBody').on('click', 'button[data-id]', function () {
            const id = this.getAttribute('data-id');
            const list = load().filter(x => x.id !== id);
            save(list); render();
        });

        // Init
        render(); updateSummary();
    });

});

//Submission Confirmation Modal
document.getElementById("affirmationForm").addEventListener("submit", function (e) {
    e.preventDefault(); // Prevent actual form submission
    $('#exampleModalCenter').modal('hide'); // Close the form modal
    setTimeout(() => {
        $('#confirmationModal').modal('show'); // Show confirmation after close
    }, 300); // Delay so animations don't overlap
});

//Affirmation form word count
document.addEventListener("DOMContentLoaded", function () {
    const messageBox = document.getElementById("exampleFormControlTextarea1");
    const form = document.getElementById("affirmationForm");

    messageBox.addEventListener("input", function () {
        let words = messageBox.value.trim().split(/\s+/).filter(Boolean);

        if (words.length > 250) {
            // Keep only the first 250 words
            messageBox.value = words.slice(0, 250).join(" ");
            alert("You can only enter up to 250 words.");
        }
    });

    // On form submit
    form.addEventListener("submit", function (e) {
        let words = messageBox.value.trim().split(/\s+/).filter(Boolean);
        if (words.length > 250) {
            e.preventDefault();
            alert("Your message exceeds the 250-word limit.");
        } else {
            // Hide the form modal
            $('#exampleModalCenter').modal('hide');
            // Show the confirmation modal after short delay
            setTimeout(() => {
                $('#confirmationModal').modal('show');
            }, 300);
        }
    });

    // Sample data for the audit log
    let auditData = [
        { date: 'Aug 4', time: '20:45', user: 'Admin', action: 'Sent', timestamp: new Date('2024-08-04 20:45') },
        { date: 'Aug 3', time: '23:22', user: 'Ava', action: 'Edited', timestamp: new Date('2024-08-03 23:22') },
        { date: 'Aug 1', time: '12:56', user: 'Ava', action: 'Edited', timestamp: new Date('2024-08-01 12:56') },
        { date: 'Jul 30', time: '14:30', user: 'Admin', action: 'Created', timestamp: new Date('2024-07-30 14:30') },
        { date: 'Jul 29', time: '09:15', user: 'John', action: 'Deleted', timestamp: new Date('2024-07-29 09:15') },
        { date: 'Jul 28', time: '16:42', user: 'Sarah', action: 'Edited', timestamp: new Date('2024-07-28 16:42') }
    ];

    let filteredData = [...auditData];
    let sortOrder = { column: -1, ascending: true };

    // Function to render the table
    function renderTable(data) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';

        data.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                    <td>${row.date}<br>${row.time}</td>
                    <td>${row.user}</td>
                    <td>${row.action}</td>
                `;
            tbody.appendChild(tr);
        });
    }

    // Function to sort table
    function sortTable(columnIndex) {
        if (sortOrder.column === columnIndex) {
            sortOrder.ascending = !sortOrder.ascending;
        } else {
            sortOrder.column = columnIndex;
            sortOrder.ascending = true;
        }

        filteredData.sort((a, b) => {
            let valA, valB;

            switch (columnIndex) {
                case 0: // Date
                    valA = a.timestamp;
                    valB = b.timestamp;
                    break;
                case 1: // User
                    valA = a.user.toLowerCase();
                    valB = b.user.toLowerCase();
                    break;
                case 2: // Action
                    valA = a.action.toLowerCase();
                    valB = b.action.toLowerCase();
                    break;
            }

            if (valA < valB) return sortOrder.ascending ? -1 : 1;
            if (valA > valB) return sortOrder.ascending ? 1 : -1;
            return 0;
        });

        renderTable(filteredData);
    }

    // Function to apply filters
    function applyFilters() {
        const startDate = document.getElementById('startDate').value;
        const endDate = document.getElementById('endDate').value;
        const userFilter = document.getElementById('userFilter').value.toLowerCase();
        const typeFilter = document.getElementById('typeFilter').value.toLowerCase();

        filteredData = auditData.filter(row => {
            let matchesUser = !userFilter || row.user.toLowerCase().includes(userFilter);
            let matchesType = !typeFilter || row.action.toLowerCase() === typeFilter;
            let matchesDate = true;

            // Simple date filtering (in a real app, you'd parse the date inputs properly)
            if (startDate && startDate.toLowerCase().includes('aug')) {
                matchesDate = matchesDate && row.date.toLowerCase().includes('aug');
            }
            if (endDate && endDate.toLowerCase().includes('jul')) {
                matchesDate = matchesDate && row.date.toLowerCase().includes('jul');
            }

            return matchesUser && matchesType && matchesDate;
        });

        renderTable(filteredData);

        // Hide filters on mobile after applying
        if (window.innerWidth <= 767) {
            const filtersContent = document.getElementById('filtersContent');
            const toggleIcon = document.getElementById('toggleIcon');
            const toggleBtn = document.querySelector('.filters-toggle');

            filtersContent.classList.remove('show');
            toggleBtn.innerHTML = '<span id="toggleIcon">▼</span> Show Filters';
        }
    }

    // Function to reset filters
    function resetFilters() {
        document.getElementById('startDate').value = '';
        document.getElementById('endDate').value = '';
        document.getElementById('userFilter').value = '';
        document.getElementById('typeFilter').selectedIndex = 0;

        filteredData = [...auditData];
        renderTable(filteredData);
    }

    // Function to export CSV
    function exportCSV() {
        const headers = ['Date', 'Time', 'User', 'Action'];
        const csvContent = [
            headers.join(','),
            ...filteredData.map(row => [
                `"${row.date}"`,
                `"${row.time}"`,
                `"${row.user}"`,
                `"${row.action}"`
            ].join(','))
        ].join('\n');

        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'audit_log.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }

    // Add search functionality on Enter key
    document.addEventListener('DOMContentLoaded', function () {
        const inputs = document.querySelectorAll('.filter-input');
        inputs.forEach(input => {
            input.addEventListener('keypress', function (e) {
                if (e.key === 'Enter') {
                    applyFilters();
                }
            });
        });
    });
});








