import { getGlobalThis } from '@util/objects';

const globalThis = getGlobalThis();

export function isOnGoogleTranslate() {
  return (
    ( globalThis.location.hostname === 'translate.googleusercontent.com' )
    || ( globalThis.location.hostname === 'translate.google.com' )
    || ( globalThis.location.pathname === '/translate_c' )
  );
}

export function getUrlBeingTranslated() {
  let urlBeingTranslated = localStorage.getItem( 'urlBeingTranslated' );
  const $base = document.querySelector( 'base[href]' );

  if (
    !urlBeingTranslated
    && $base
    && ( $base.href.indexOf( '/metrolist/' ) !== -1 )
  ) {
    urlBeingTranslated = $base.getAttribute( 'href' );
  }

  return urlBeingTranslated;
}

export function copyGoogleTranslateParametersToNewUrl( url ) {
  let newUrl = '';
  const isBeingTranslated = isOnGoogleTranslate();

  if ( isBeingTranslated ) {
    const metrolistGoogleTranslateIframeUrl = localStorage.getItem( 'metrolistGoogleTranslateIframeUrl' );

    if ( metrolistGoogleTranslateIframeUrl ) {
      newUrl = metrolistGoogleTranslateIframeUrl.replace(
        /([a-z]+=)(?:https?:\/\/[^/]+\/metrolist\/[^&?=]*)(.*)/i,
        `$1${url}$2`,
      );
    } else {
      console.error( 'Could not find `metrolistGoogleTranslateIframeUrl` in localStorage' );
    }
  } else {
    console.error( 'Google Translate URL not detected (checked for translate.googleusercontent.com, translate.google.com, and /translate_c). Can not copy query parameters to new Google Translate URL.' );
  }

  return newUrl;
}

// Fix for Google Translate iframe shenanigans
// @location - React Router useLocation instance
export function resolveLocationConsideringGoogleTranslate( location, isBeingTranslated ) {
  isBeingTranslated = ( isBeingTranslated || isOnGoogleTranslate() );
  let resolvedUrlPath = location.pathname;
  let urlBeingTranslated = null;

  if ( isBeingTranslated && location.search.length ) {
    const filteredQueryParameters = location.search.split( '&' ).filter( ( urlParameter ) => urlParameter.indexOf( '/metrolist/' ) !== -1 );

    /* istanbul ignore else */
    if ( filteredQueryParameters.length ) {
      urlBeingTranslated = filteredQueryParameters[0].replace( /[a-z]+=(https?:\/\/[^/]+\/metrolist\/.*)/i, '$1' );
      resolvedUrlPath = urlBeingTranslated.replace( /https?:\/\/[^/]+(\/metrolist\/.*)/i, '$1' );

      localStorage.setItem( 'metrolistGoogleTranslateIframeUrl', globalThis.location.href );
      localStorage.setItem( 'urlBeingTranslated', urlBeingTranslated );
    }
  }

  if ( !urlBeingTranslated ) {
    const $base = document.querySelector( 'base[href]' );

    /* istanbul ignore else */
    if ( $base && ( $base.href.indexOf( '/metrolist/' ) !== -1 ) ) {
      urlBeingTranslated = $base.href;
    }
  }

  return {
    ...location,
    "pathname": resolvedUrlPath,
    "_urlBeingTranslated": urlBeingTranslated,
  };
}

export function switchToGoogleTranslateBaseIfNeeded( $base ) {
  localStorage.removeItem( 'metrolistGoogleTranslateUrl' );

  $base = ( $base || document.querySelector( 'base[href]' ) );
  const googleTranslateBaseUrl = ( ( $base && isOnGoogleTranslate() && $base ) ? globalThis.location.origin : null );

  // Fix CORS issue with history.push routing inside of Google Translate
  if ( googleTranslateBaseUrl ) {
    $base.href = googleTranslateBaseUrl;
  }
}

export function switchBackToMetrolistBaseIfNeeded( resolvedLocation, $base ) {
  localStorage.removeItem( 'metrolistGoogleTranslateUrl' );

  $base = ( $base || document.querySelector( 'base[href]' ) );
  const metrolistBaseUrl = ( ( $base && isOnGoogleTranslate() ) ? resolvedLocation._urlBeingTranslated : null ); // Added by Google to correct links, but breaks React Router

  // Fix CORS issue with history.push routing inside of Google Translate
  if ( metrolistBaseUrl ) {
    $base.href = metrolistBaseUrl;
  }
}
