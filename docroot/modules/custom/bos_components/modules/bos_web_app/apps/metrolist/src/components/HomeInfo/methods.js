import React from 'react';
import dayjs from 'dayjs';
import { capitalize } from '@util/strings';

export function formatKey( { key, value, info } ) {
  let formattedKey;

  switch ( key ) {
    case 'listingDate':
      formattedKey = 'Posted: ';
      break;
    case 'applicationDueDate':
      formattedKey = 'Application Due: ';
      break;
    case 'assignment':
      break;
    case 'incomeRestricted':
      if ( value === true ) {
        formattedKey = 'Income-restricted';

        if ( info.assignment ) {
          formattedKey += ' – ';
        }
      } else {
        formattedKey = 'Market Price';
      }
      break;
    default:
      if ( typeof key === 'string' ) {
        formattedKey = `${capitalize( key )}: `;
      } else {
        formattedKey = `${key}: `;
      }
      break;
  }

  return formattedKey;
}

export function formatValue( { key, value, info } ) {
  let formattedValue;
  let date;

  if (
    ( key === 'listingDate' )
    || ( key === 'applicationDueDate' )
  ) {
    if ( value ) {
      date = dayjs( value );
    }
  }

  switch ( key ) {
    case 'listingDate':
      formattedValue = <time dateTime={ date.toISOString() }>{ date.format( 'M/D/YY' ) }</time>;
      break;
    case 'applicationDueDate':
      formattedValue = ( value ? <time dateTime={ date.toISOString() }>{ date.format( 'M/D/YY' ) }</time> : null );
      // formattedValue = null;
      break;
    case 'assignment':
      break;
    case 'incomeRestricted':
      if ( value === true ) {
        switch ( info.assignment ) {
          case 'lottery':
            formattedValue = 'Housing lottery';
            break;
          case 'waitlist':
            formattedValue = 'Open waitlist';
            break;
          case 'first':
            formattedValue = 'First come, first served';
            break;
          default:
            if ( typeof info.assignment === 'string' ) {
              formattedValue = capitalize( info.assignment );
            } else {
              formattedValue = `${value}`;
            }
        }
      } else {
        if ( typeof value === 'string' ) {
          formattedValue = capitalize( value );
        } else {
          formattedValue = `${value}`;
        }
      }
      break;
    default:
      if ( typeof key === 'string' ) {
        formattedValue = capitalize( key );
      } else {
        formattedValue = `${value}`;
      }
      break;
  }

  return formattedValue;
}
