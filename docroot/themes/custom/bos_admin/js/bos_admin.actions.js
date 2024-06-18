/**
* @file
*/

/**
 * Shows additional buttons when page length extends.
 */
(function ($, window, document) {
  'use strict';
  $(document).ready(function () {
    var actions = $(".bos_admin .layout-region-node-main #edit-actions");
    if (actions.length) {
      var showActions = function () {
        $(".bos_admin .vertical-tabs__pane").each(function () {
          var blockTop = $(".bos_admin .layout-region-node-main").position().top;
          if ($(this).is(":visible")) {

            if (($(this).height() + blockTop) > $(window).height()) {
              actions.slideDown();
            }
            else {
              actions.slideUp();
            }
          }
        });
      };
      $(".bos_admin .vertical-tabs__menu-item, .bos_admin summary, .bos_admin .paragraphs-actions input.button").click(function () {
        setTimeout(function () {
          showActions();
        }, 500);
      });
      $(document).ajaxStop(function () {
        showActions();
      });
      showActions();
    }
  });
})(jQuery, this, this.document);

/**
 * Adds a checkbox to datetime_range on public notices.
 */
(function ($, window, document) {
  'use strict';
  $(document).ready(function () {
    var actions = $(".field--name-field-public-notice-date #cbx-field-end-date");
    if (actions.length) {
      actions.click(function () {
        $(this).parent().next().show();
        $(this).parent().hide();
      });
    }
  });
})(jQuery, this, this.document);

/**
 * Stops RHS Info Box from scrolling off page.
 */
(function ($, window, document) {
  $(document).ready(function () {
    let anchor = jQuery(".dialog-off-canvas-main-canvas").position().top + 20;
    $(document).on("scroll", function () {
      if ($(document).scrollTop() > anchor) {
        $(".layout-region-node-secondary").addClass("fixed");
      }
      else {
        $(".layout-region-node-secondary").removeClass("fixed");
      }
    });
  });
})(jQuery, this, this.document);
