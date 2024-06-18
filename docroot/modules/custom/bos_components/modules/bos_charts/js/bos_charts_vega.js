jQuery(document).ready(function ($) {
  var opt = {
    "renderer": "svg",
    "actions": false
  };
  for(const chartid in drupalSettings.cob.charts) {
    var chart = drupalSettings.cob.charts[chartid];
    var VegaLiteSpec = JSON.parse(chart.chartobj);

    // Replace dataURL in object if provided.
    if (typeof VegaLiteSpec.data === "undefined") {
      VegaLiteSpec.data = {};
    }
    if (typeof chart.dataType !== "undefined" && typeof chart.data !== "undefined") {
      if (chart.dataType == "url") {
        delete VegaLiteSpec.data.values;
        VegaLiteSpec.data.url = chart.data;
      }
      else if (chart.dataType == "json_values") {
        delete VegaLiteSpec.data.url;
        delete VegaLiteSpec.data.format;
        VegaLiteSpec.data.values = JSON.parse(chart.data);
      }
    }

    // Ensure the background is set to "none" for theming.
    if (typeof VegaLiteSpec.config === "undefined") {
      VegaLiteSpec.config = {};
    }
    VegaLiteSpec.config.background = "none";

    console.log(VegaLiteSpec);

    vegaEmbed('#' + chart.chartid , VegaLiteSpec, opt)
      .then(function (result) {
      })
      .catch(console.error);
  }
});
