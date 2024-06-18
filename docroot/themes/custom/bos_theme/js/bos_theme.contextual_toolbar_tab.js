/**
 * @file
 * Tab Functionality.
 *
 * Emulates a click on the "Edit" contextual toolbar "button" and then hides the tab.
 */

(function ($, Drupal, window, document) {

  'use strict';
  $(document).ready(function () {
    if (!$(".contextual-toolbar-tab button").hasClass("is-active")) {
      // Make sure the toolbar tab is clicked.
      $(".contextual-toolbar-tab").click();
    }
    $(".contextual-toolbar-tab").hide();
    $("#toolbar-bar").addClass("toolbar-space");
  });

})(jQuery, Drupal, this, this.document);
