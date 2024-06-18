## Authentication

### ENVARS
GOOGLE_CLOUD_PROJECT => Project ID

GOOGLE_APPLICATION_CREDENTIALS => contains path to a json file

### Settings
If using Drupal settings, raed the settings and then use putenv() to set the ENVAR - because the API uses getenv()
to read authentication settings/variables.

@see https://github.com/googleapis/google-cloud-php/blob/main/AUTHENTICATION.md

---

##
