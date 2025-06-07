jQuery(document).ready(function ($) {
  $("#publish").on("click", function (e) {
    if (!confirm("Are you sure you want to start this email campaign?")) {
      e.preventDefault();
    }
  });
  $(".ec-pause-btn, .ec-resume-btn, .ec-cancel-btn").on("click", function () {
    var action = $(this).data("action");
    var campaignId = $(this).data("campaign");
    $.post(
      ajaxurl,
      {
        action: "ec_manage_scheduler",
        campaign_id: campaignId,
        manage: action,
      },
      function (resp) {
        location.reload();
      }
    );
  });
});
