/*
  Replacement for Object.hasOwnProperty()
  to satisfy eslint(no-prototype-builtins)
*/
export function hasOwnProperty( object, property ) { // eslint-disable-line import/prefer-default-export
  return Object.prototype.hasOwnProperty.call( object, property );
}

export function isPlainObject( value ) {
  return (
    ( value !== null )
    && ( typeof value === "object" )
    && ( Object.getPrototypeOf( value ) === Object.prototype )
  );
}

// Naive globalThis polyfill. Not suitable for all use cases but good enough to get IE working.
// Read more: https://mathiasbynens.be/notes/globalthis
export function getGlobalThis() {
  if ( typeof globalThis !== 'undefined' ) {
    return globalThis;
  }

  if ( typeof window !== 'undefined' ) {
    return window;
  }

  if ( typeof global !== 'undefined' ) {
    return global;
  }

  throw new Error( 'Can’t find global object' );
}
