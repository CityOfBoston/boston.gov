/**
 * @file
 */

/**
 * Manages autocompletion of related contents views and display link.
 */
(function ($, window, document) {
  'use strict';

  var ResetrelatedFieldView = function (obj) {
    if ($("#edit-field-related-wrapper input.form-autocomplete").hasClass("notEmpty")) {
      $(".viewLink").remove();
    }
  }

  var relatedFieldView = function (obj) {
      $("#edit-field-related-wrapper input.form-autocomplete").each(function () {
        if ($(this).val() != "" && $(this).val().indexOf('(') > -1) {
          // Split and grab the node id to create a link to view
          $(this).addClass("notEmpty");
            const string = $(this).val();
            const nodeNum = (string.split("(")[1].split(")")[0]);
            const fullUrl = "https://boston.gov/node/" + nodeNum;
            console.log(nodeNum);
            $(this).after('<a target="_blank" class="viewLink" style="padding-left: 10px;" href="' + fullUrl + '">View</a>');
        }
        else {
          $(this).addClass("Empty");
          relatedFieldChangeView();
        }
      });
  }

  var relatedFieldChangeView = function (obj) {
    $("#edit-field-related-wrapper input.form-autocomplete").change( function () {
      ResetrelatedFieldView();
      relatedFieldView();
    });
  }

  window.setTimeout(function () {
    relatedFieldView();
  }, 500);

  $( document ).ajaxStop(function() {
    $("#edit-field-related-wrapper input.field-add-more-submit").addClass('active-btn');
    ResetrelatedFieldView();
    relatedFieldView();
  });

})(jQuery, this, this.document);
