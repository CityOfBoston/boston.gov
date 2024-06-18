import PropTypes from 'prop-types';
import { date, dateTime } from './datetime';

export const unitObject = PropTypes.shape( {
  "id": PropTypes.string.isRequired,
  "bedrooms": PropTypes.number,
  "amiQualification": PropTypes.number,
  "price": PropTypes.number,
  "priceRate": PropTypes.oneOf( ['monthly', 'once'] ),
} );

export const homeObject = PropTypes.shape( {
  "id": PropTypes.string.isRequired,
  "slug": PropTypes.string,
  "url": PropTypes.string,
  "title": PropTypes.string,
  "listingDate": dateTime,
  "applicationDueDate": date,
  "assignment": PropTypes.oneOf( [null, '', 'lottery', 'waitlist', 'first'] ),
  "city": PropTypes.string,
  "neighborhood": PropTypes.string,
  "type": PropTypes.oneOf( [null, 'apt', 'single-family', 'sro', 'condo', 'multi-family'] ),
  "offer": PropTypes.oneOf( ['rent', 'sale'] ),
  "units": PropTypes.arrayOf( unitObject ),
  "incomeRestricted": PropTypes.bool,
} );

export const filtersObject = PropTypes.shape( {
  "offer": PropTypes.shape( {
    "rent": PropTypes.bool,
    "sale": PropTypes.bool,
  } ),
  "location": PropTypes.shape( {
    "city": PropTypes.shape( {
      "boston": PropTypes.bool,
      "beyondBoston": PropTypes.bool,
    } ),
    "neighborhood": PropTypes.objectOf( PropTypes.bool ),
    "cardinalDirection": PropTypes.shape( {
      "west": PropTypes.bool,
      "north": PropTypes.bool,
      "south": PropTypes.bool,
    } ),
  } ),
  "bedrooms": PropTypes.shape( {
    "0": PropTypes.bool,
    "1": PropTypes.bool,
    "2": PropTypes.bool,
    "3+": PropTypes.bool,
  } ),
  "amiQualification": PropTypes.shape( {
    "lowerBound": PropTypes.number,
    "upperBound": PropTypes.number,
  } ),
  "rentalPrice": PropTypes.shape( {
    "lowerBound": PropTypes.number,
    "upperBound": PropTypes.number,
  } ),
} );
