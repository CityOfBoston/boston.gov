(function ($, Drupal, drupalSettings) {
  Drupal.behaviors.bosSearchDynamicLoader = {
    attach: function (context, settings) {

      var scriptPath = drupalSettings.bos_search.dynamic_script;
      var cssPath = drupalSettings.bos_search.dynamic_style;

      // Function to load a JavaScript file
      function loadScript(url) {
        var script = document.createElement('script');
        script.src = url;
        document.head.appendChild(script);
      }

      // Function to load a CSS file
      function loadCSS(url) {
        var link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        document.head.appendChild(link);
      }

      // Load resources if they are defined
      const element = $('.aienabledsearchbutton', context);
      if (element.length > 0 && !element.attr('data-once-loadPresetJS')) {
        element.attr('data-once-loadPresetJS', true);
        if (scriptPath) {
          loadScript(scriptPath);
        }
        if (cssPath) {
          loadCSS(cssPath);
        }
      }
      else {
        const element = $('.aienabledsearchform', context);
        if (element.length > 0 && !element.attr('data-once-loadPresetJS')) {
          element.attr('data-once-loadPresetJS', true);
          if (scriptPath) {
            loadScript(scriptPath);
          }
          if (cssPath) {
            loadCSS(cssPath);
          }
        }
      }
    }
  };
})(jQuery, Drupal, drupalSettings);
