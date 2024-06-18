/**
 * @file
 * A JavaScript file for moderation sidebar textarea.
 */
(function ($, Drupal, window, document) {
  $(document).on('click', '.moderation-sidebar-quick-transition-form input.button', chkSubmit);

  function chkSubmit() {
    var msg = $('.moderation-sidebar-revision-log');
    if (msg.val() != null && msg.val() != '') {
      $('.moderation-sidebar-revision-log').css('border', 'transparent');
    }
    else {
      $('.moderation-sidebar-revision-log').css('border', '3px solid red');
      $('.moderation-sidebar-revision-log').attr('placeholder','Please provide a revision log message.')
      return false;
    }
  }
})(jQuery, Drupal, this, this.document);
