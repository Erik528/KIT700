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

            if (words.length > 35) {
                const short = words.slice(0, 35).join(' ') + '…';
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
});


