# Abutters App

## Built with
* [React](https://reactjs.org/)
* [mapboxGL](https://www.mapbox.com/mapbox-gl-js/api/)
* [esri-leaflet](https://esri.github.io/esri-leaflet/)

## Basic Functionality

This application lets users search for an address or click on the map to select a parcel. The user can then buffer the selected parcel and get ownership information for all parcels that intersect the buffer.

The parcel data leveraged in the map is hosted on [BostonMaps](http://boston.maps.arcgis.com/home/index.html). 

We leverage [ESRI's buffer api](https://developers.arcgis.com/rest/services-reference/buffer.htm) to buffer the selected parcel. 

At a highlevel we are going through the following steps:
1. Letting people search for an address or click on the map to select a parcel.
2. Using the location of that click/address/parcel id to query our hosted parcel layer (referenced above) and figure out which parcel the user has clicked on/inside. 
3. Using an ESRI buffer api (referenced above) to buffer the selected parcel and display the buffered geometry on the map.
4. Using that buffer shape we query the parcel layer again and find all parcels that intersect with it (e.g. finding all parcels within x feet of the selected one).
5. Gathering the ownership information from the intersecting parcels and put it into a csv users can download.
