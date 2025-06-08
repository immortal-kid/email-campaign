jQuery(document).ready(function ($) {
  // Campaign action buttons (Pause, Resume, Cancel)
  $(document).on("click", ".ec-pro-campaign-action-btn", function (e) {
    e.preventDefault();

    var $button = $(this);
    var actionType = $button.data("action"); // pause, resume, cancel
    var postId = $button.data("post-id");
    var originalButtonHtml = $button.html();

    if (!postId) {
      alert("Error: Campaign ID not found.");
      return;
    }

    if (actionType === "cancel") {
      if (
        !confirm(
          "Are you sure you want to cancel this campaign? All pending emails will be unscheduled."
        )
      ) {
        return;
      }
    }

    $button.prop("disabled", true).text("Processing..."); // Disable button and show loading

    $.ajax({
      url: ecProAdminVars.ajax_url,
      type: "POST",
      data: {
        action: "ec_pro_" + actionType + "_campaign", // e.g., 'ec_pro_pause_campaign'
        post_id: postId,
        nonce: ecProAdminVars.nonce,
      },
      success: function (response) {
        if (response.success) {
          alert(response.data.message);
          // Update status in the meta box (if on edit screen)
          var statusLabel = $("#ec-pro-status-label");
          if (statusLabel.length) {
            statusLabel.text(response.data.status_label);
          }
          // Update progress message (if on edit screen)
          var progressMessage = $("#ec-pro-progress-message");
          if (progressMessage.length) {
            if (response.data.new_status === "in_progress") {
              progressMessage.text("Currently sending..."); // Will be updated by refresh later
            } else if (response.data.new_status === "paused") {
              progressMessage.text("Campaign is paused.");
            } else if (response.data.new_status === "cancelled") {
              progressMessage.text("Campaign has been cancelled.");
            }
          }

          // On edit screen, re-render buttons based on new status
          var statusContainer = $button.closest(
            "#ec-pro-campaign-status-display"
          );
          if (statusContainer.length) {
            // For simplicity, just reload the meta box content
            // In a more complex setup, you might dynamically toggle buttons.
            // For now, reload the page to refresh UI state correctly.
            location.reload();
          } else {
            // On list table, just refresh the page for full UI update
            location.reload();
          }
        } else {
          alert("Error: " + response.data.message);
          $button.prop("disabled", false).html(originalButtonHtml); // Re-enable on error
        }
      },
      error: function () {
        alert("An AJAX error occurred.");
        $button.prop("disabled", false).html(originalButtonHtml); // Re-enable on error
      },
    });
  });

  // Handle "Select Contacts from Database" button (currently informative only)
  $("#ec_pro_select_existing_contacts").on("click", function (e) {
    e.preventDefault();
    $("#ec-pro-existing-contacts-selector").slideToggle();
  });
});
