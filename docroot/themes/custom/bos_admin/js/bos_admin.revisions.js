/**
 * @file
 */

(function ($, window, document) {
  'use strict';
  $(document).ready(function () {
    var radios = $(".bos_admin #edit-node-revisions-table .form-radio");
    var radiosLeft = $(".bos_admin .form-item-radios-left .form-radio");
    var radiosRight = $(".bos_admin .form-item-radios-right .form-radio");
    var reset = $(".bos_admin input.compare-reset");
    var clearRadios = function () {
      radios
        .css({"visibility": "visible"})
        .prop({"checked": false});
    };
    radios.click(function () {
      var state = ($(this).is(":checked") ? "hidden" : "visible");
      if ($(this).parent().hasClass("form-item-radios-left")) {
        radiosLeft.css({"visibility": state});
      }
      else if ($(this).parent().hasClass("form-item-radios-right")) {
        radiosRight.css({"visibility": state});
      }
      $(this).css({"visibility": "visible"});
    });
    reset.click(function (e) {
      e.preventDefault();
      clearRadios();
    });
  });
})(jQuery, this, this.document);
