(function ($, Drupal, once) {
  Drupal.behaviors.searchDisclaimer = {
    attach: function (context, settings) {
      $(document).ready(function(){
        var disclaimerform = $('.aisearch-disclaimer-form .ui-button');
        if (disclaimerform.length && !disclaimerform.attr('disclaimer-once')) {
          disclaimerform.click(function (event) {
            event.preventDefault();
            Drupal.dialog("#drupal-modal").close();
          });
          disclaimerform.attr({ 'disclaimer-once': true })
        }
      });

      const element = $('.aienabledsearchform', context);

      // Only display the disclaimer once per form display
      if (element.length > 0 && !element.attr('data-once-searchDisclaimer')) {
        element.attr('data-once-searchDisclaimer', true);

        // Callback to create the disclaimer.
        if (settings.disclaimerForm.triggerDisclaimerModal) {
          Drupal.ajax({
            url: settings.disclaimerForm.openModal,
          }).execute();

          $(document).on("ajaxComplete", function(event, xhr, settings) {
            // Fires when the disclaimer form is returned by ajax
            $('.aienableddisclaimerform .btn-submit').click(function (event) {
              event.preventDefault();
              Drupal.dialog("#drupal-modal").close();
            });
          });

        }

      }
    }
  };
})(jQuery, Drupal, once);
