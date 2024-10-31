(function ($, Drupal, once) {
  Drupal.behaviors.aiSearch = {
    attach: function (context, settings) {
      var new_height = 0;
      once('loadExample', '.bos-search-aisearchform .card', context).forEach(
        function(element){
          if (!new_height) {
            new_height = find_max_height($(element.parentElement).children());
          }
          $(element).css({"min-height": new_height});
          $(element).on("click", function(event) {
            $(".bos-search-aisearchform .search-bar").val($(element).find(".card-content").text());
            submit_form();
          });
        }
      );
      once('aiSearch', '.bos-search-aisearchform #search-bar-submit', context).forEach(
        function (element) {
          $(element).click(function (event) {
            event.preventDefault();
            submit_form();
          });
        }
      );
      once('aiSearch2', '.bos-search-aisearchform .search-bar', context).forEach(
        function (element) {
          $(element).keyup(function (event) {
            if(event.originalEvent.key === "Enter") {
              $('.bos-search-aisearchform #search-bar-submit').click();
            }
          });
        }
      );
      once('ajaxMonitor', '.aienabledsearchform', context).forEach(
        function(element){
          $(document).on("ajaxError", function(event, xhr, settings, thrownError) {
            event.preventDefault();
            var searchform = $('.aienabledsearchform');
            searchform.find(".search-bar")
              .removeClass("searching")
              .removeAttr('disabled')
              .val('')
              .focus();

            if (drupalSettings.user.uid !== 0) {
              console.log('Custom AJAX Error Handler: An error occurred.');
              console.log('Error details:', xhr.responseText);
            }
          });
          $(document).on("ajaxComplete", function(event, xhr, settings) {
            var searchform = $('.aienabledsearchform');
            var this_request = searchform.find(".search-request").last();
            var this_response = searchform.find(".search-response-text").last();
            var this_citations = searchform.find(".search-citations-wrapper").last();

            if (xhr.statusText.toString() === 'success') {
              toggle_welcome_block(xhr.responseJSON);
              limit_citations_height(this_response, this_citations);
              toggle_citations_show_more(this_response, this_citations);
            }
            searchform.find('.search-request-progress-wrapper').remove();
            move_div_to_top(searchform, this_request);
            searchform.find(".search-bar")
              .removeAttr('disabled')
              .removeClass("searching")
              .val("")
              .focus();
          });
        }
      );

    },

  };

  var submit_form = function () {

    var searchform = $('.aienabledsearchform');

    if (validate_form(searchform)) {

      var welcome_block = searchform.find('#edit-welcome');
      // var search_bar=searchform.find("#search-bar-submit");

      add_request_bubble(searchform);
      var this_request = searchform.find(".search-request-wrapper").last();
      move_div_to_top(searchform, this_request);

      if (welcome_block.length > 0) {
        collapse_welcome_block(searchform);
      }

      searchform.find("input.form-submit").mousedown();

      searchform.find('.search-bar')
        .attr("disabled",'')
        .addClass('searching')
        .val(drupalSettings.bos_search.waiting_text)
        .focus();

    }
    else {
      searchform.find('.search-bar-input input').focus();
    }

  }

  var validate_form = function (searchform) {
    if (searchform.find('.search-bar').val()) {
      return true;
    }
    return false;
  }

  var add_request_bubble = function(searchform) {
    var request_text = searchform.find('.search-bar').val();
    searchform.find('#search-conversation-wrapper').append("" +
      "<div class=\"search-request-wrapper\">" +
      "<div class=\"search-request\">" + request_text + "</div>" +
      "<div class=\"clearfix\"></div>" +
      "<div class=\"search-request-progress-wrapper\">" +
      "<div class=\"search-request-progress\"></div>" +
      "<div class=\"search-request-progress\"></div>" +
      "</div>" +
      "</div>" +
      "<div class=\"clearfix\"></div>");
  }

  var collapse_welcome_block = function(searchform) {
    searchform.find('#edit-welcome').slideUp('slow', function() {
      searchform.animate({
        scrollTop: searchform.prop('scrollHeight')
      }, 'fast');
    });
  }

  var toggle_welcome_block = function (responses) {

    var mainAction;

    $(responses).each(function (index, element) {
      if (element.command === 'insert' && typeof mainAction === 'undefined') {
        mainAction = element;
      }
    });

    if (mainAction && mainAction.command === 'insert') {
      // Looks like the ajax command succeeded.
      if (mainAction.dialogOptions) {
        // This is a modal form.
        var classes = mainAction.dialogOptions.classes["ui-dialog"];
        if (classes && (!classes.match("aienableddisclaimerform") && !classes.match("feedback-dialog"))) {
            // this is the main search form.
            $('.bos-search-aisearchform').addClass('no-welcome');
        }
      }
      else if (mainAction.selector !== '#drupal-modal') {
        // Not a modal form, and not using the modal selector therefore we are appending results.
        $('.bos-search-aisearchform').addClass('no-welcome');
      }
    }

  }

  var limit_citations_height = function(response, citations) {
    var drawer = citations.find(".search-citations-drawer");
    while (response && drawer && response.height() < (drawer.height() - 40)) {
      var elem = drawer.find('.search-citation:not(".hidden"):not(".search-citation-more")').last()
      if (elem.length === 0){
        return;
      }
      elem.addClass("hidden").css({"display":"none"});
      drawer.addClass("show-more");
    }
  }

  var toggle_citations_show_more = function(response, citations) {
    var drawer = citations.find(".search-citations-drawer");
    if (citations.length && drawer.hasClass("show-more")) {
      drawer
        .find('.search-citation-more')
          .on("click", function(e){
            e.preventDefault();
            drawer.removeClass("show-more")
              .css({"overflow-y": "scroll"})
              .find(".search-citation.hidden")
                .removeClass("hidden")
                .css({"display": "block"});
        });
      drawer.css({"max-height": response.height() + "px"});
      drawer.find("dr-c").css({"min-height": response.height() + "px"});
    }
  }

  var move_div_to_top = function(searchform, div) {
    if ($(".search-response-wrapper").length) {

      var site_banner = $('.site-banner');
      site_banner = site_banner.length ? site_banner.height() : 0;

      var offsetHeight = ((div.offset().top) - (searchform.offset().top) -  site_banner);
      var scroll_layer = $("html, body");

      if (searchform.hasClass("aisearch-modal-form")) {
        scroll_layer = searchform;
        offsetHeight = ((div.offset().top) - (searchform.offset().top) - site_banner);
      }

      scroll_layer.animate({
        scrollTop: offsetHeight,
      }, 'fast');

    }
  }

  var find_max_height = function(elements) {
    var max_example_height = 0;
    elements.each(function(idx, element) {
      if (max_example_height < $(element).outerHeight()) {
        max_example_height = $(element).outerHeight();
      }
    });
    return max_example_height + "px";
  }

})(jQuery, Drupal, once);
