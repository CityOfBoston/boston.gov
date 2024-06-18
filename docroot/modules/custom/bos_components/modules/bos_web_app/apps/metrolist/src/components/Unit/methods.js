import React from 'react';
import NumberFormat from 'react-number-format';

export function formatSize( bedrooms, numberOfIdenticalUnits ) {
  let formattedSize = '';

  if ( bedrooms > 0 ) {
    formattedSize = <>
      <abbr title={ `${bedrooms} Bedroom` } className="ml-shorthand ml-hide-at-large">{ `${bedrooms} BR` }</abbr>
      <span className="ml-hide-until-large">{ `${bedrooms} Bedroom` }</span>
      { numberOfIdenticalUnits && ` (×${numberOfIdenticalUnits})` }
    </>;
  } else {
    formattedSize = 'Studio';

    if ( numberOfIdenticalUnits ) {
      formattedSize += ` (×${numberOfIdenticalUnits})`;
    }
  }

  return formattedSize;
}

export function formatAmiQualification( amiQualification ) {
  if ( amiQualification === null ) {
    return <>
      <abbr className="ml-shorthand ml-hide-at-large">N/A</abbr>
      <span className="ml-hide-until-large">Not Applicable</span>
    </>;
  }

  return `${amiQualification}% AMI`;
}

export function formatPrice( price, priceRate, rentalPriceIsPercentOfIncome ) {
  return (
    <NumberFormat
      value={ price }
      displayType={ 'text' }
      prefix={ '$' }
      thousandSeparator={ true }
      renderText={ ( value ) => {
        const isForSale = ( priceRate === 'once' );

        if ( isForSale ) {
          return <>{ value }</>;
        }

        if ( rentalPriceIsPercentOfIncome ) {
          return 'Variable';
        }

        const abbreviationExpansion = `${value} per ${priceRate.substring( 0, 5 )}`;

        return (
          <abbr className="ml-shorthand" title={ abbreviationExpansion }>
            { `${value}/` }
            <span className="ml-unit__price-rate">{ priceRate.substring( 0, 2 ) }.</span>
          </abbr>
        );
      } }
    />
  );
}
