// File: assets/js/scheduler.js
(function ($) {
  /**
   * Handles pause, resume, and cancel actions for a campaign.
   * Buttons should have classes .ec-pause-btn, .ec-resume-btn, .ec-cancel-btn
   * and data attributes: data-action="pause|resume|cancel" and data-campaign="<ID>".
   */
  function manageCampaign(action, campaignId) {
    $.post(ajaxurl, {
      action: "ec_manage_scheduler",
      campaign_id: campaignId,
      manage: action,
      _ajax_nonce: EC_Scheduler.nonce,
    })
      .done(function (response) {
        if (response.success) {
          location.reload();
        } else {
          alert("Error: " + (response.data || "Unknown error"));
        }
      })
      .fail(function (xhr) {
        alert("AJAX error: " + xhr.statusText);
      });
  }

  $(document).ready(function () {
    // Pause button
    $(document).on("click", ".ec-pause-btn", function (e) {
      e.preventDefault();
      var campaignId = $(this).data("campaign");
      if (confirm("Pause this campaign?")) {
        manageCampaign("pause", campaignId);
      }
    });

    // Resume button
    $(document).on("click", ".ec-resume-btn", function (e) {
      e.preventDefault();
      var campaignId = $(this).data("campaign");
      if (confirm("Resume this campaign?")) {
        manageCampaign("resume", campaignId);
      }
    });

    // Cancel button
    $(document).on("click", ".ec-cancel-btn", function (e) {
      e.preventDefault();
      var campaignId = $(this).data("campaign");
      if (
        confirm(
          "Cancel this campaign? All remaining emails will be unscheduled."
        )
      ) {
        manageCampaign("cancel", campaignId);
      }
    });
  });
})(jQuery);
