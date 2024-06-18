  /**
   * Unofficial election results
   * Sort results into preferred order on page.
   * 11/2022
   */
  (function ($, document) {
    'use strict';
    function getSorted(selector, attrName) {
      return $($(selector).toArray().sort(function(a, b){
        var aVal = parseInt(a.getAttribute(attrName)),
          bVal = parseInt(b.getAttribute(attrName));
        return aVal - bVal;
      }));
    }
    jQuery(document).ready(function () {
      let sorted = getSorted(".cob-election-contest", "data-sort");
      let results = $(".area-results-all");
      results.find(".cob-election-contest").remove();
      results
        .append(
          $("<div>")
            .addClass("b b--fw bg--g000")
            .attr("bos_context_type", "Election Contest Results")
            .attr("id", "0")
        )
        .append(sorted);
    });
  })(jQuery, this.document);

  /**
   * Unofficial election results
   * Filter election results based on select option value
   * 10/19/2022
   */
  (function ($) {
    'use strict';
    $('#election_results').on('change', function() {
      var url = $(this).val(); // get selected value
      let result = $('.cob-election-contest');
      result.hide().removeClass('bg--g000').removeClass("b--g");
      $("#"+url).show().addClass('b--g');
      if (url === "all") {
        result.show().addClass('bg--g000');
      }
      return false;
    });
  })(jQuery);
