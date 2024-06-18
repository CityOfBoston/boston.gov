import React from 'react';
import dayjs from 'dayjs';
import { capitalCase } from 'change-case';

export function wasJustListed( listingDate, unitOfTime = 'hours', newnessThreshold = 48 ) {
  // testing:
  // return true;

  const now = dayjs();
  const then = dayjs( listingDate );
  const diff = now.diff( then, unitOfTime );

  if ( diff <= newnessThreshold ) {
    return true;
  }

  return false;
}

export function renderJustListed( listingDate ) {
  if ( wasJustListed( listingDate ) ) {
    return <b className="ml-home__just-listed" data-column-width="1/4">Just listed!</b>;
  }

  return null;
}

export function renderOffer( offer ) {
  offer = offer.toLowerCase();

  switch ( offer ) {
    case 'rental':
      return 'For Rent';

    case 'sale':
      return 'For Sale';

    default:
      return null;
  }
}

export function renderType( type ) {
  if ( !type ) {
    return type;
  }

  type = type.toLowerCase();

  switch ( type ) {
    case 'apt':
      return 'Apartment';

    case 'sro':
      return 'Single Room Occupancy';

    case 'condo':
      return 'Condominium';

    case 'multi-family':
      return 'Multi-family';

    default:
      return capitalCase( type );
  }
}

export function serializeFiltersToUrlParams( filters, home ) {
  // /metrolist/search/listing/275-roxbury-street?ami=30-120&bedrooms=1+2&type=rent
  // /metrolist/search/listing/{slug}?ami={ami_low}-{ami_high}&bedrooms={num_beds}+{num_beds}&type={offer}
  const params = [];
  const { amiQualification, bedrooms } = filters;

  if ( amiQualification ) {
    let amiParam = 'ami=';

    if ( amiQualification.lowerBound ) {
      amiParam += amiQualification.lowerBound;
    } else {
      amiParam += '0';
    }

    amiParam += '-';

    if ( amiQualification.upperBound ) {
      amiParam += amiQualification.upperBound;
    } else {
      amiParam += '200';
    }

    if ( amiParam !== 'ami=0-200' ) {
      params.push( amiParam );
    }
  }

  if ( bedrooms ) {
    let bedroomsParam = 'bedrooms=';
    let preferredBedroomSizeCount = 0;

    Object.keys( bedrooms ).forEach( ( bedroomSize, index ) => {
      const bedroomSizeToggled = bedrooms[bedroomSize];

      if ( bedroomSizeToggled ) {
        if ( ( index > 0 ) && ( preferredBedroomSizeCount > 0 ) ) {
          bedroomsParam += '+';
        }

        preferredBedroomSizeCount++;
        bedroomsParam += bedroomSize.replace( /\+/g, '' ); // TODO: Question for Alex: does 4 work for 4+?
      }
    } );

    if ( preferredBedroomSizeCount ) {
      params.push( bedroomsParam );
    }
  }

  // Since single developments can be split into two virtual “homes” by the API, then
  // we need to tell the Property Page which version of a home it should display when
  // the user clicks on More Info.
  if ( home ) {
    if ( home.offer ) {
      params.push( `type=${home.offer}` );
    }

    if ( home.assignment ) {
      params.push( `assignment=${home.assignment}` );
    }
  }

  if ( params.length ) {
    return `?${params.join( '&' )}`;
  }

  return '';
}
