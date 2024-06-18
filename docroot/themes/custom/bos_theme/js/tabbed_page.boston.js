/**
 * @file
 * Tab Functionality.
 *
 * Provides tab functionality for the site.
 * Primarily used on How To pages and Tabbed Content Pages.
 */

(function ($, Drupal, window, document) {

  'use strict';
  if ($('ul.content-section-tabs').length) {
    $(function () {
      // Get hash from URL.
      var hash = window.location.hash;
      if (hash) {
        var elementID = hash.replace('#', '');
        $('div.tab-content[id="' + elementID + '"]').addClass('is-active');
        $('ul.tabs li[data-tab="' + elementID + '"]').addClass('is-active');
      }
      else {
        $('ul.content-section-tabs li').first().addClass('is-active').find('a').addClass('is-active');
        $('.tab-content').first().addClass('is-active');
      }

      if (location.hash) {
        setTimeout(function () {

          window.scrollTo(0, 0);
        }, 1);
      }

    });

    $('ul.tabs li').click(function () {
      var tab_id = $(this).attr('data-tab');

      $('ul.tabs li').removeClass('is-active').find('a').removeClass('is-active');
      $('.tab-content').removeClass('is-active');

      $(this).addClass('is-active').find('a').addClass('is-active');
      $("#" + tab_id).addClass('is-active');
    });
  }

  if ($('ul.content-section-tabs').length) {
    $('ul.content-section-tabs li a').on('click', function (e) {
      e.preventDefault();
    });
  }

})(jQuery, Drupal, this, this.document);
