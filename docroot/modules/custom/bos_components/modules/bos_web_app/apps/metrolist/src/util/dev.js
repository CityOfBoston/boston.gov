import { getGlobalThis } from '@util/objects';

const globalThis = getGlobalThis();

export function isProd( hostname = globalThis.location.hostname ) {
  return (
    ( hostname === 'wwww.boston.gov' )
    || ( hostname === 'boston.gov' )
  );
}

export default function isDev() {
  return !isProd();
}

export function isLiveDev( urlOrDomain = globalThis.location.hostname ) {
  return /^(?:https?:\/\/)?metrolist\.netlify\.app/.test( urlOrDomain );
}

export function isLocalDev( hostname = globalThis.location.hostname ) {
  return (
    ( hostname === 'localhost' )
    || ( hostname === '127.0.0.1' )
    || /\b(?:\d{1,3}\.){3}\d{1,3}\b/.test( hostname ) // is IPv4 address
  );
}

export function getApiDomain() {
  let devApi;

  if (typeof drupalSettings.cob.baseUrl !== "undefined") {
    return `https://${drupalSettings.cob.baseUrl}`;
  }

  if ( globalThis.location.search ) {
    devApi = globalThis.location.search.split( '&' ).filter( ( part ) => part.indexOf( '_api=' ) !== -1 ).join( '' );

    if ( devApi ) {
      devApi = `https://d8-${devApi.replace( '?_api=', '' )}.boston.gov`;
    }
  }

  if ( isLocalDev() && !devApi ) {
    devApi = 'https://d8-ci.boston.gov';
  }

  return ( devApi || '' );
}

export function getDevelopmentsApiEndpoint() {
  return `${getApiDomain()}/metrolist/api/v1/developments?_format=json`;
  // return `/metrolist/api/v1/developments?_format=json`;
}

export function getAmiApiEndpoint() {
  return `${getApiDomain()}/metrolist/api/v1/ami/hud/base?_format=json`;
  // return `/metrolist/api/v1/ami/hud/base?_format=json`;
}
