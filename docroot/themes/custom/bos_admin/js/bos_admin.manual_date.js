/**
 * @file
 */

(function ($, window, document) {
  'use strict';
  $(document).ready(function () {
    var metadata = $("#edit-group-page-meta-data");
    if (metadata.length) {
      var checkbox = metadata.find("#edit-field-manual-date-value");
      var inputboxes = metadata.find("input.form-date, input.form-time");
      var enabled = !checkbox.is(":checked");
      var setEnabled = function (obj) {
        enabled = obj.is(":checked");
        inputboxes.each(function () {
          $(this).prop({"disabled": !enabled});
        });
      };
      checkbox.click(function () {
        setEnabled($(this));
        if (enabled) {
          alert("WARNING:\nSetting this checkbox will stop automated management of Updated and Published dates.\nThis means the value displayed on this article webpage will be the one you set in the fields below.");
        }
      });
      setEnabled(checkbox);
    }
  });
})(jQuery, this, this.document);
