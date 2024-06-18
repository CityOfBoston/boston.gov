/**
 * @file
 * Lightning task sidebar Functionality.
 *
 * Moves the tasks button into the watermark.
 */

'use strict';

(function ($) {
  const targetClass = ".wm-ops";
  const msClass = ".moderation-sidebar-toolbar-tab";
  var jumpButton = function () {
    if ($(targetClass).length) {
      var target = $(targetClass);
      jQuery(targetClass).find(msClass).remove();
      if ($(msClass).length) {
        var ms = $(msClass);
        ms.prop("style", "")
          .find("a")
            .prop("style", "")
            .text("Edit");
        target.append(ms);
      }
    }
  };
  $(document).ready(function () {
    jumpButton();
  });
})(jQuery);
