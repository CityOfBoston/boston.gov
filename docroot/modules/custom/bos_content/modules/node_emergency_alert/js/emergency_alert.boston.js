/**
 * @file
 */

(function ($) {
    Drupal.behaviors.bosContentTypeEmergencyAlert = {
        'attach': function (context) {
            $('#statusHeader').once("emergency-override", function () {
                $.get("/emergency-alert?" + Math.round(new Date().getTime() / 1000), null, callback);
            });
        }
    };

    var callback = function (response) {
        if (response.emergency) {
            var element = $('#statusHeader');
            element.html(response.html);
        }
    };
})(jQuery);
