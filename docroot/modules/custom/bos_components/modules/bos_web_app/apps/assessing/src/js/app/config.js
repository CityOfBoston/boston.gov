const configProps = {
  "globals": {
    // Sets URL path for browser history functionality in REACT application.
    "path": window.location.pathname,
    // Sets URL path for image icons in REACT application.
    "pathImage": "https://assets.boston.gov/icons/experiential_icons/",
  },
  "frame_google": function () {
    const page = location.href;
    const pageGoogle = page.toLowerCase().indexOf("google");
    const pageTranslate = page.toLowerCase().indexOf("translate");
    if (pageGoogle >= 0 || pageTranslate >= 0) {
      return true;
    } else {
      return false;
    }
  }
};
