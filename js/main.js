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


