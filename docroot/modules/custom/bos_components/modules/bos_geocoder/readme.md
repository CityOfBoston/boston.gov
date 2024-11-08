# Geocoder Utility
This module allows forward (address->co-ords) and reverse (co-ords->address) lookups
for addresses from the CoB ArcGIS and/or from Google (GCP) Geocoders.

The module can be configured to use either service, or set up to query ArcGIS
first, and use Google as a fall-back.

## Use

The query of the geocoders is performed in Drupal services. Drupal (PHP) code
can utilize these services (classes) and perform server-side processes to
query address information directly.
- To use the ArcGIS geocoder only, use the class `Drupal\bos_geocoder\Services\ArcGisGeocoder`.
- To use the GCP geocoder only, use the class `Drupal\bos_google_cloud\Services\GcGeocoder` (bos_google_cloud service)
- To use the ArcGis with fallback to GCP, use `Drupal\bos_geocoder\Controller\BosGeocodeController`

### REST
This utility configured as an endpoint/microservice, to make geocoding available
to front-end (javascript) Ajax calls.  However, the server-side can also leverage this
using Curl/Guzzle from a pre-process/validation hook/callback or an entity event.

# Geocoder Services
## ArcGIS
CoB maintain a directory of Geocode information which can be accessed by:
- Street Address (inc postcode) -> Map Co-ords
- Parcel # -> Map Co-ords
- Map Co-ordinates -> Street Address

_This service only operates for addresses in the City of Boston metropolitan area._
## Google
The Google geocode service allows
- US_wide (& global?) Street Address (inc postcode) -> Map Co-ords
- Map Co-ordinates -> Street Address
_This service is paid-for and there is a small cost per lookup._

