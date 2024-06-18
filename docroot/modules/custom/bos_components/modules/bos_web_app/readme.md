This is intended to house JS-based webapps within the Drupal ecosystem
# For each web app to be included
## New custom Drupal module
1. A new custom module should be created in the `modules/custom/bos_components/modules/bos_web_app/apps` folder in the boston.gov (drupal) repository. The folder and the module should have the exact same name and be a relevant to the apps function (in these notes we'll use `my_web_app`). The new folder (`.. bos_web_app/apps/my_web_app`) must contain, at minimum, the following files:
   * `my_web_app.info.yml` the Drupal info file,
   * `my_web_app.module` the Drupal main code file,
   * `my_web_app.libraries.yml` the file which embeds the JS code, and
   * `composer.json` file which provides composer info for the project (actually optional but is best practice).
2. The `libraries.yml` file should contain configuration to include js and css packaged with the my_web_app js application.
>Note: 1. The top level name is important and must be the same as the folder name. 2. The version is important. When a new version of the JS app is released, this version number need to be incremented or else Drupal will continue to use the current version it has cached.
```yaml
my_web_app:
  version: scheduling.123456
  js:
    apps/my_web_app/dist/index.js: {attributes: {type: text/javascript}}
  css:
    layout:
      apps/my_web_app/dist/styles.css: {attributes: {media: screen, type: text/css}}
  dependencies:
    - core/drupalSettings
```

## Configuration updates to bos_web_app
***There should be no need to alter any code in bos_web_app***

## Updates to main Drupal `config.json` file.
The `composer.json` needs to be modified to download the JS code from its repository and include it in the docroot for the Drupal site.
1. Need to add 2 repository entries for the GitHub repository to the file. One entry will downoad the js-build (bin) code and one will download the source code.  The source code is only needed for local development.
```yaml
        {
            "type": "package",
            "package": {
                "name": "cityofboston/my_web_app_dev",
                "version": "1.0",
                "type": "drupal-custom-module",
                "source": {
                    "url": "git@github.com:CityOfBoston/my-web-app.git",
                    "type": "git",
                    "reference": "main"
                }
            }
        },
        {
            "type": "package",
            "package": {
                "name": "cityofboston/my_web_app",
                "version": "1.0",
                "type": "drupal-custom-module",
                "dist": {
                    "url": "https://github.com/CityOfBoston/my-web-app/archive/1.0.zip",
                    "type": "zip"
                }
            }
        }
```
2. Need to add a `require` and `require-dev` entry to include the bin and source code.
```yaml
"require": {
                 ...
  "cityofboston/my_web_app": "^1.0",
                 ...
}
"require-dev": {
                 ...
  "cityofboston/my_web_app_dev": "^1.0",
                 ...
}
```
NOTE: The `composer/installers` extension will install the js into the js folder of the `bos_web_app/apps/my-web-app` folder.

## Built-in requirements on the JS App side:
1. The JS app should be contained within a City of Boston (or public) GitHub repository
2. **CSS** Should leverage css from the COB patterns library: https://patterns.boston.gov
3. **Initializing** The js should be initialized
2. **Anchoring** Should use a simple anchor, an `div` with an id of `web-app`.
> Should another id be required, then the id can be added to the Drupal module like this:
```
  if ($vars["elements"]["field_app_name"][0]["#context"]["value"] == "[app_name]") {
    if (!isset($vars["webapp_anchor"])) {
      $vars["webapp_anchor"] = new Drupal\Core\Template\Attribute();
    }
    $vars["webapp_anchor"]->setAttribute("id", "[custom-id");
  }
```
