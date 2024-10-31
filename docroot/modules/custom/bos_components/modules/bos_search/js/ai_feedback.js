(function ($, Drupal, once) {
  Drupal.behaviors.ai_search_feedback = {
    attach: function (context, settings) {
      once('feedbackForm', '.ai-feedback-wrapper', context).forEach(
        function (element) {
          $(document).on("ajaxComplete", function (event, xhr, settings) {
            if (xhr.statusText.toString() === 'success') {
              var thisdialog = $('.feedback-dialog');
              if (settings.url.toString().startsWith("/form/ai-search-feedback") && thisdialog.length > 0) {
                if (thisdialog.find(".text-count-message").length > 0) {
                  var more = thisdialog.find('textarea[name=tell_us_more]');
                  more.on("keyup", function(element){textarea_counter(element.target, thisdialog);})
                  var submission = $(".aienabledsearchform").find("#search-conversation-wrapper");
                  var question = submission.find(".search-request").last().html();
                  var summary = submission.find(".search-response-text").last().html();
                  if (question) {
                    thisdialog.find('.search-question').val(question);
                  }
                  if (summary) {
                    thisdialog.find('.search-summary').val(summary);
                  }
                }
                else {
                  var message = thisdialog.text().trim("\n");
                  message = message.replace("Close","").trim(' ');
                  $(".aienabledsearchform .ai-feedback-confirm").last().text(message).show();
                  $(".aienabledsearchform .ai-feedback-buttons").last().hide();
                  var searchform = $('.aienabledsearchform');
                  var thumbs = $('.ai-feedback-wrapper').last();
                  move_div_to_middle(searchform, thumbs);
                  $("#drupal-modal").dialog("close");
                }
              }
            }
          });
        }
      );
    }
  };

  var textarea_counter = function (element, thisdialog) {
    var textbox = $(element).val();
    var count = parseInt(textbox.length);
    if (!count) {
      thisdialog.find('.text-count-message').text('200 characters allowed');
    }
    else {
      thisdialog.find('.text-count-message').text((200 - count) + ' characters remaining');
    }
  };

  var move_div_to_middle = function(searchform, div) {
    if ($(".search-response-wrapper").length) {
      var offsetHeight = ((div.offset().top) - (searchform.offset().top) - ($(window).height() / 3));
      var scroll_layer = $("html, body");
      if (searchform.hasClass("aisearch-modal-form")) {
        scroll_layer = searchform;
        offsetHeight = ((div.offset().top) - (searchform.offset().top) - window.height() );
      }
      scroll_layer.animate({
        scrollTop: offsetHeight,
      }, 'fast');
    }
  }

  var add_click_to_feedback = function (element) {
    $(element).on("click", function (event) {
      even.peventDefault();
      var form = $(".ai-feedback-wrapper");
    });
  }
  
})(jQuery, Drupal, once);
