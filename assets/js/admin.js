(function ($) {
  $(function () {
    // Confirmation modal on 'Publish'
    if ($("#publish").length) {
      $("#publish").on("click", function (e) {
        if (!confirm("Are you sure you want to start this email campaign?")) {
          e.preventDefault();
        }
      });
    }

    // Upload-and-import
    $("#ec_upload_btn").on("click", function () {
      const file = $("#ec_contacts_file")[0].files[0];
      if (!file) {
        alert("Choose a CSV/XLSX first");
        return;
      }

      const fd = new FormData();
      fd.append("action", "ec_upload_contacts");
      fd.append("security", EC_Ajax.nonce);
      fd.append("post_id", $("#post_ID").val());
      fd.append("contacts_file", file);

      $("#ec_upload_btn").prop("disabled", true).text("Uploading…");

      $.ajax({
        url: EC_Ajax.ajax_url,
        method: "POST",
        data: fd,
        processData: false,
        contentType: false,
      })
        .done((res) => {
          const d = res.data; // payload lives here
          $("#ec_upload_result").html(
            `<p><strong>${d.valid}</strong> valid, ` +
              `${d.invalid} invalid, ${d.duplicates} duplicates</p>`
          );
        })
        .fail((xhr) => {
          alert(xhr.responseJSON ? xhr.responseJSON.data : "Upload failed");
        })
        .always(() => {
          $("#ec_upload_btn").prop("disabled", false).text("Upload & Import");
        });
    });
  });
})(jQuery);
