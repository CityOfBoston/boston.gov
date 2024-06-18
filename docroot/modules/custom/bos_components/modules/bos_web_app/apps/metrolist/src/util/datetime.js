// References:
// https://www.oreilly.com/library/view/regular-expressions-cookbook/9781449327453/ch04s07.html

import { propTypeErrorMessage } from './errors';

// XML Schema: date (ISO 8601)
export function isDate( dateString ) {
  const dateRegex = /^(-?(?:[1-9][0-9]*)?[0-9]{4})-(1[0-2]|0[1-9])-(3[01]|0[1-9]|[12][0-9])(Z|[+-](?:2[0-3]|[01][0-9]):[0-5][0-9])?$/;
  return dateRegex.test( dateString );
}

export function date( props, propName, componentName ) {
  const prop = props[propName];

  if ( !prop ) {
    return null; // Handle empty/null
  }

  if ( !isDate( prop ) ) {
    return new Error(
      propTypeErrorMessage( {
        propName,
        componentName,
        "got": prop,
        "expected": "xs:date string (ISO8601)",
        "example": new Date().toISOString().split( 'T' )[0],
      } ),
    );
  }

  return null;
}

// XML Schema: time (ISO 8601)
export function isTime( timeString ) {
  const timeRegex = /^(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])(\.[0-9]+)?(Z|[+-](?:2[0-3]|[01][0-9]):[0-5][0-9])?$/;
  return timeRegex.test( timeString );
}

export function time( props, propName, componentName ) {
  const prop = props[propName];

  if ( !isTime( prop ) ) {
    return new Error(
      propTypeErrorMessage( {
        propName,
        componentName,
        "got": prop,
        "expected": "xs:time string (ISO 8601)",
        "example": new Date().toISOString().split( 'T' )[1],
      } ),
    );
  }

  return null;
}

// XML Schema: dateTime (ISO 8601)
export function isDateTime( dateString ) {
  const dateTimeRegex = /^(-?(?:[1-9][0-9]*)?[0-9]{4})-(1[0-2]|0[1-9])-(3[01]|0[1-9]|[12][0-9])T(2[0-3]|[01][0-9]):([0-5][0-9]):([0-5][0-9])(\.[0-9]+)?(Z|[+-](?:2[0-3]|[01][0-9]):[0-5][0-9])?$/;
  return dateTimeRegex.test( dateString );
}

export function dateTime( props, propName, componentName ) {
  const prop = props[propName];

  if ( !isDateTime( prop ) ) {
    return new Error(
      propTypeErrorMessage( {
        propName,
        componentName,
        "got": prop,
        "expected": "xs:dateTime string (ISO 8601)",
        "example": new Date().toISOString(),
      } ),
    );
  }

  return null;
}
