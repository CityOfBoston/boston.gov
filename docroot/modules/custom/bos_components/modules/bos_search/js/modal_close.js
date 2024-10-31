(function ($, Drupal, once) {
  Drupal.behaviors.modal_close = {
    attach: function (context, settings) {
      once('modal_close', '#drupal-modal .modal-close', context).forEach(
        function (element) {
          $(element).click(function (event) {
            Drupal.dialog("#drupal-modal").close();
          });
        }
      );
      once('modal_reset', '.aienabledsearchform .ai-form-reset', context).forEach(
        function (element) {
          $(element).click(function (event) {
            // $('.bos-search-aisearchform').removeClass('no-welcome');
            var searchform = $('.aienabledsearchform');
            searchform.find('[name=session_id]').val("");
            searchform.find('#search-conversation-wrapper')
              .fadeOut('fast', function(){
                searchform.find('#search-conversation-wrapper').empty().show();
                searchform.find('#edit-welcome').slideDown('fast');
                searchform.removeClass("has-results");
                searchform.find("input.search-bar").removeAttr('disabled').focus();
              });
          });
        }
      );
      once('resetAi', '.aienabledsearchform', context).forEach(
        function(element){
          $(document).on("ajaxComplete", function(event, xhr, settings) {
            var searchform = $('.aienabledsearchform');
            if (xhr.statusText.toString() === 'success' &&
               drupalSettings.has_results) {
              if (!searchform.hasClass('has-results')) {
                searchform
                  .addClass('has-results')
              }
            }
          });
        }
      );

    },
  };
})(jQuery, Drupal, once);
