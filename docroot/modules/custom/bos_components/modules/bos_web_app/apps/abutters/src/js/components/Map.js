import React from "react";
import PropTypes from 'prop-types';
import bbox from '@turf/bbox';
import { configVals } from '../app/config.js';
import Filters  from './Filters';

// We can't import these server-side because they require "window"
const MapboxGeocoder = process.browser
  ? require('@mapbox/mapbox-gl-geocoder')
  : null;
const mapboxgl = process.browser ? require('mapbox-gl') : null;
// Despite using mapboxgl to render the map, we still use esri-leaflet to
// query to the layer
const { featureLayer, request, Util } = process.browser
  ? require('esri-leaflet')
  : {};

const L = process.browser ? require('leaflet') : {};

// We set up the ESRI feature service URL for parcels.
const parcels_url =
  'https://services.arcgis.com/sFnw0xNflSi8J0uh/ArcGIS/rest/services/AbutterParcels_Oct2020/FeatureServer/0';

class Map extends React.Component {
  componentDidMount() {
    this.parcelFeatureLayer = featureLayer({
      url: parcels_url,
    });

    // We update the selected parcel in three circumstances:
    //  1. Someone clicks on the map
    //  2. A searched address location is within a parcel.
    //  3. Someone searches for a parcel ID.
    // The function works the same for scenarios 1 and 2 above - we query which
    // parcel the searched address location and/or the clicked location falls within
    // so we break it into its own function.
    const setSelectedParcel = eventLocation => {
      // We use Esri Leaflet to query the parcel feature layer. We're
      // specifically asking for what parcel contains the clicked location.
      this.parcelFeatureLayer
        .query()
        .contains(eventLocation)
        .run((error, featureCollection) => {
          if (error) {
            // eslint-disable-next-line no-console
            console.error(error);
            return;
          }
          // The parcel layer will have parcels that overlap each other, but they all
          // use the same master parcel ID and address, since those two pieces of
          // information are all we need right now, we select the first one.
          const selectedParcel = featureCollection.features[0];
          // So that we can display the selected parcel ID on the side of the map,
          // we pass the change to the MapContainer component which can then pass
          // it down to the Filters component.
          this.props.handleParcelChange(selectedParcel);

          // If the user didn't click a location outside the parcel layer's
          // geometry, we highlight the parcel they selected.
          if (selectedParcel !== undefined) {
            this.map.getSource('highlight').setData(selectedParcel.geometry);
            this.map.setLayoutProperty(
              'highlight-line',
              'visibility',
              'visible'
            );
            this.map.setLayoutProperty(
              'highlight-polygon',
              'visibility',
              'visible'
            );
          } else {
            this.props.handleBufferParcels('');
            this.map.setLayoutProperty('highlight-line', 'visibility', 'none');
            this.map.setLayoutProperty(
              'highlight-polygon',
              'visibility',
              'none'
            );
          }
        });
    };

    this.map = new mapboxgl.Map({
      container: this.mapContainer,
      center: [-71.067449, 42.352568],
      zoom: 13,
      style: {
        version: 8,
        // Despite mapbox enabling vector basemaps, we use our own tiled
        // basemap service to keep with city styling and branding
        sources: {
          'esri-grey': {
            type: 'raster',
            tiles: [
              'https://services.arcgisonline.com/ArcGIS/rest/services/Canvas/World_Light_Gray_Base/MapServer/tile/{z}/{y}/{x}',
            ],
            tileSize: 256,
          },
          'cob-basemap': {
            type: 'raster',
            tiles: [
              'https://awsgeo.boston.gov/arcgis/rest/services/Basemaps/BostonCityBasemap_WM/MapServer/tile/{z}/{y}/{x}',
            ],
            tileSize: 256,
          },
        },
        layers: [
          {
            id: 'esri-grey',
            type: 'raster',
            source: 'esri-grey',
            minzoom: 0,
            maxzoom: 24,
          },
          {
            id: 'cob-basemap',
            type: 'raster',
            source: 'cob-basemap',
            minzoom: 0,
            maxzoom: 24,
          },
        ],
      },
    });

    // In order to add a geocoder to our map, we need a mapbox access token.
    // We've stored that using a global config file
    const accessToken = configVals().publicRuntimeConfig.MapboxAccessToken;

    const geocoder = new MapboxGeocoder({
      accessToken: accessToken,
      flyTo: {
        bearing: 0,
        speed: 5, // Make the flying faster.
        curve: 1, // Change the speed at which it zooms out.
      },
      placeholder: 'Search for an address…',
      country: 'us',
      // We set a bounding box so that the geocoder only looks for
      // matches in and around Boston, MA.
      // We need the minX, minY, maxX, maxY in that order.
      bbox: [-71.216812, 42.226992, -70.986099, 42.395573],
      zoom: 18,
    });

    // We want the geocoder div to show up in the Filters component so we've added
    // a div there with the id "geocoder". Here we're appending the mapbox
    // geocoder we just set up to that div.
    document.getElementById('geocoder').appendChild(geocoder.onAdd(this.map));

    this.map.on('load', () => {
      // When the map loads, we do a lot of of setting up empty layers so that
      // we have layers to update when the user starts interacting with the map.
      // The order that these layers are added in defines their drawing order on
      // the map, so we add the ones we want on the bottom first.

      // For starters, we load up the icon we're using for showing geocoder results.
      this.map.loadImage(
        'https://patterns.boston.gov/images/global/icons/mapping/waypoint-freedom-red.svg',
        (error, image) => {
          if (error)
            // eslint-disable-next-line no-console
            console.error(
              'Could not load red waypoint icon. Error message:',
              error
            );
          this.map.addImage('red-waypoint', image);
        }
      );

      // We do the same thing as above for the parcels that intersect the
      // buffer - add one source and two layers (line and poylgon) for
      // styling.
      this.map.addSource('buffer-parcels', {
        type: 'geojson',
        data: {
          type: 'FeatureCollection',
          features: [],
        },
      });

      this.map.addLayer({
        id: 'buffer-parcels-polygon',
        type: 'fill',
        source: 'buffer-parcels',
        layout: {},
        paint: {
          'fill-color': '#288BE4',
        },
        minzoom: 0,
        maxzoom: 24,
      });

      this.map.addLayer({
        id: 'buffer-parcels-line',
        source: 'buffer-parcels',
        type: 'line',
        paint: {
          'line-width': 2.5,
          'line-color': '#1f6eb5',
        },
        layout: {
          'line-cap': 'round',
          'line-join': 'round',
        },
      });

      // We add another empty geojson source for the buffer polygon. We'll
      // use this layer to display the buffer around the selected parcel.
      // We add a fill and line layer that both use this same source so we have
      // more granular control over the styling.
      this.map.addSource('buffer', {
        type: 'geojson',
        data: {
          type: 'FeatureCollection',
          features: [],
        },
      });

      this.map.addLayer({
        id: 'buffer-polygon',
        source: 'buffer',
        type: 'fill',
        paint: {
          'fill-color': '#D2D2D2',
          'fill-outline-color': '#58585B',
          'fill-opacity': 0.5,
        },
      });

      this.map.addLayer({
        id: 'buffer-line',
        source: 'buffer',
        type: 'line',
        paint: {
          'line-width': 2,
          'line-color': '#58585B',
        },
        layout: {
          'line-cap': 'round',
          'line-join': 'round',
        },
      });

      // Since we're using lines and polygons to represent the parcels, we want
      // features of all geometries to get highlighted when a user clicks on them,
      // we add two more layers: a highlight-line layer and a highlight-polygon layer.
      this.map.addSource('highlight', {
        type: 'geojson',
        data: {
          type: 'FeatureCollection',
          features: [],
        },
      });

      this.map.addLayer({
        id: 'highlight-line',
        source: 'highlight',
        type: 'line',
        paint: {
          'line-width': 6,
          'line-color': '#FB4D42',
        },
        layout: {
          'line-cap': 'round',
          'line-join': 'round',
        },
      });

      this.map.addLayer({
        id: 'highlight-polygon',
        source: 'highlight',
        type: 'fill',
        paint: {
          'fill-color': '#FB4D42',
          'fill-outline-color': '#FB4D42',
          'fill-opacity': 0.5,
        },
      });

      // Finally, we add an empty geojson source and layer that we'll populate
      // with the results of the geocoding search when appropriate.
      this.map.addSource('geocoding-result-point', {
        type: 'geojson',
        data: {
          type: 'FeatureCollection',
          features: [],
        },
      });

      this.map.addLayer({
        id: 'geocoding-result',
        source: 'geocoding-result-point',
        type: 'symbol',
        layout: {
          visibility: 'none',
          // We use the icon image we loaded above here.
          'icon-image': 'red-waypoint',
          'icon-size': 0.25,
        },
      });

    });

    // When the geocoder finds a result, we add our red-waypoint icon
    // to the map at the addresses' location and update the selected parcel.
    geocoder.on('result', function(ev) {
      geocoder._map
        .setLayoutProperty('geocoding-result', 'visibility', 'visible')
        .getSource('geocoding-result-point')
        .setData(ev.result.geometry);
      setSelectedParcel(ev.result.geometry);
    });

    this.map.on('click', e => {
      // When the user clicks in a new location on the map, we clear
      // the existing layers.
      this.map.setLayoutProperty('geocoding-result', 'visibility', 'none');
      this.map.setLayoutProperty(
        'buffer-parcels-polygon',
        'visibility',
        'none'
      );
      this.map.setLayoutProperty('buffer-parcels-line', 'visibility', 'none');
      this.map.setLayoutProperty('buffer-polygon', 'visibility', 'none');
      this.map.setLayoutProperty('buffer-line', 'visibility', 'none');

      // We convert the longitude/latitude of the click location to a
      // Leaflet point object so that we can use it to figure out which
      // parcel was clicked on.
      const clickLocation = L.latLng(e.lngLat);
      setSelectedParcel(clickLocation);
    });
  }

  componentDidUpdate(prevProps) {
    if (
      prevProps.searchForParcelIDButtonClicked !=
      this.props.searchForParcelIDButtonClicked
    ) {
      const query = `PID_LONG = '${this.props.searchedParcelID}'`;

      this.parcelFeatureLayer
        .query()
        .where(query)
        .run((error, featureCollection) => {
          if (error) {
            // eslint-disable-next-line no-console
            console.error(error);
            return;
          }
          // By definition, the spatial parcel layer has no parcels that overlap
          // eachother, so we're safe to select the first feature in the returned
          // collection of them.
          const selectedParcel = featureCollection.features[0];

          // We update state so that we can use the selected parcel information in
          // componenets.
          this.props.handleParcelChange(selectedParcel);

          // If they searchced for a parcel we have, we highlight and zoom to it.
          if (selectedParcel !== undefined) {
            this.map.getSource('highlight').setData(selectedParcel.geometry);

            const layerBounds = bbox(selectedParcel);
            this.map.fitBounds(layerBounds, { padding: 200 });
          } else {
            return;
          }
        });
    } else if (
      prevProps.bufferButtonClicked != this.props.bufferButtonClicked
    ) {
      if (this.props.selectedParcel != undefined) {
        // We use the ESRI rest api to buffer the selected parcel. It requires
        // inputs in the ESRI json format, so we leverage esri leaflet to convert
        // the geojson we have to the necessary format.
        const esriJson = Util.geojsonToArcGIS(this.props.selectedParcel);
        request(
          'https://awsgeo.boston.gov/arcgis/rest/services/Utilities/Geometry/GeometryServer/buffer',
          {
            geometries: {
              geometryType: 'esriGeometryPolygon',
              // We pass just the geometry of the feature to the request as an array.
              geometries: [esriJson.geometry],
            },
            // Our map is in Web Mercator, so we indicate that is what we are passing
            // and what we want back.
            inSR: 4326,
            outSR: 4326,
            // We want the actual buffer to be calculated in 2249 (the MA State Plane
            // NAD 1983 Feet coordinate system). This is much more reliable for calculations
            // MA than 4326.
            bufferSR: 2249,
            // We use the buffer distance the user enters
            distances: this.props.bufferDistance,
            // We set the unit of this distances to US Survery Feet.
            unit: 9003,
            unionResults: true,
          },
          (error, response) => {
            if (error) {
              // eslint-disable-next-line no-console
              console.error(error);
              return;
            }
            // An array of geometries is returned, since we're always unioning the results
            // (see param above), we can confidently always select the first result in the
            // array as our buffer.
            // We have to convert that back to geojson for it to be displayed on our map.
            const bufferPoly = Util.arcgisToGeoJSON(response.geometries[0]);
            this.map.getSource('buffer').setData(bufferPoly);

            // After we have the new buffer, we update the visability of the
            // relevant map layers.
            this.map.setLayoutProperty(
              'buffer-polygon',
              'visibility',
              'visible'
            );
            this.map.setLayoutProperty('buffer-line', 'visibility', 'visible');

            // After we have the buffer, we use its geometry to find the parcels
            // that intersect it.
            this.parcelFeatureLayer
              .query()
              .intersects(bufferPoly)
              .run((error, featureCollection) => {
                if (error) {
                  // eslint-disable-next-line no-console
                  console.error(error);
                  return;
                }
                this.map.getSource('buffer-parcels').setData(featureCollection);
                // After we set the new source, we make sure the layers displaying the
                // buffered parcels correctly update the map.
                this.map.setLayoutProperty(
                  'buffer-parcels-polygon',
                  'visibility',
                  'visible'
                );
                this.map.setLayoutProperty(
                  'buffer-parcels-line',
                  'visibility',
                  'visible'
                );

                // We save them to our state so we can use them in the filter
                // component when we are building the mailing list CSV the user
                // will download.
                this.props.handleBufferParcels(featureCollection.features);
              });
          }
        );
      } else {
        return;
      }
    }
  }

  componentWillUnmount() {
    this.map.remove();
  }

  render() {
    (console.log('map'));
    return (
      <div>
        {/* make map take up entire viewport with room for the navbars */}
        <div style={{ height: '100vh' , width: "100%"}} ref={el => (this.mapContainer = el)}>
          <div style={{ zIndex: 1000, position: 'relative !important' }}></div>
        </div>
      </div>
    );
  }
}

export default Map;

Map.propTypes = {
  handleParcelChange: PropTypes.func,
  selectedParcel: PropTypes.object,
  bufferDistance: PropTypes.number,
  handleBufferParcels: PropTypes.func,
  bufferButtonClicked: PropTypes.bool,
  searchedParcelID: PropTypes.string,
  searchForParcelIDButtonClicked: PropTypes.bool,
};
