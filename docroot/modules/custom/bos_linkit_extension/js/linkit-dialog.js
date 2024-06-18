/**
 * @file
 */

/**
 * Manages radion buttons on Email/Phone tab on link dialog.
 */
(function ($, window, document) {
  'use strict';

  var cob_linkitShowSpinner = function (show) {
    if (show === true) {
      $("body").append($("<div>").addClass("cob-spinner"));
    }
    else {
      $(".cob-spinner").remove();
    }
  };

  var cob_linkitShowFieldSet = function (obj) {
    var index = $(obj).val().toString();
    $(".cob-radio-frameset").hide();
    $(".cob-radio-".concat(index)).show();
  };

  var cob_linkitResetForm = function (obj) {
    var index = 0;
    $(obj).parent().children().each(function (i) {
      if (obj === this) {
        index = i;
      }
    });
    $(".cob-details-wrapper").each(function (j) {
      if (j !== index) {
        $(this).find("input").find('[type="text"], [type="textarea"]').val("");
      }
      if (j === 1) {
        var obj = $(".cob-radio-wrapper:checked");
        if (obj.length !== 0) {
          cob_linkitShowFieldSet(obj);
        }
      }
    });

    index = $("#drupal-modal .horizontal-tab-button.selected").index();
    $("#drupal-modal #edit-current").val(index);
    $(document).trigger('scroll');
  };

  var cob_linkitCreateLinks = function () {
    $("#drupal-modal .bos-boxed-content-t .form-radio")
      .off('click touchstart')
      .on('click touchstart', function () {
        cob_linkitShowFieldSet(this);
      });
    $("#drupal-modal .horizontal-tab-button")
      .off('click touchstart')
      .on('click touchstart', function () {
        cob_linkitResetForm(this);
      });
    $("#drupal-modal .horizontal-tab-button-".concat($("#drupal-modal #edit-current").val())).find("a").click();
    window.setTimeout(function () {
      $("#drupal-modal .horizontal-tab-button-".concat($("#drupal-modal #edit-current").val())).find("a").click();
      cob_linkitShowSpinner(false);
    }, 500);
  };

  window.setTimeout(function () {
    cob_linkitShowSpinner(true);
    cob_linkitCreateLinks();
  }, 500);

  $(".cke_button__drupallink_icon").on("click touchstart", function () {
    window.setTimeout(function () {
      cob_linkitShowSpinner(true);
    }, 500);
    window.setTimeout(function () {
      cob_linkitShowSpinner(true);
      cob_linkitCreateLinks();
    }, 2000);
  });

  $( document ).ajaxStop(function () {
    cob_linkitShowSpinner(true);
    cob_linkitCreateLinks();
  });

})(jQuery, this, this.document);
