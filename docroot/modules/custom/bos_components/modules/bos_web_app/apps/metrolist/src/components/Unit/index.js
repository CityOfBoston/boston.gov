import React from 'react';
import PropTypes from 'prop-types';

import { unitObject } from '@util/validation';
import { formatSize, formatAmiQualification, formatPrice } from './methods';

import './Unit.scss';

function Unit( { unit, percentageOfIncomeExplanationId } ) {
  const {
    id, bedrooms, count, amiQualification, price, priceRate,
  } = unit;

  const rentalPriceIsPercentOfIncome = ( ( price === null ) || price === 'null' );

  /*
    Order:
      1. Size
      2. Qualification
      3. Price
  */
  return (
    <tr className="ml-unit" data-testid={ id }>
      <td className="ml-unit__cell ml-unit__size">
        <div className="ml-unit__cell-inner">{ formatSize( bedrooms, count ) }</div>
      </td>
      <td className="ml-unit__cell ml-unit__income-limit">
        <div className="ml-unit__cell-inner">{ formatAmiQualification( amiQualification ) }</div>
      </td>
      <td className="ml-unit__cell ml-unit__price" aria-labelledby={ rentalPriceIsPercentOfIncome ? percentageOfIncomeExplanationId : null }>
        <div className="ml-unit__cell-inner">{ formatPrice( price, priceRate, rentalPriceIsPercentOfIncome ) }</div>
      </td>
    </tr>
  );
}

Unit.propTypes = {
  "unit": unitObject,
  "percentageOfIncomeExplanationId": PropTypes.string,
};

export default Unit;
