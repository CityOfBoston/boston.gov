import React from 'react';
import PropTypes from 'prop-types';

import Unit from '@components/Unit';

import { unitObject } from '@util/validation';

import './UnitGroup.scss';

function UnitGroup( { units, percentageOfIncomeExplanationId } ) {
  return (
    <table className="ml-unit-group">
      <caption className="sr-only">Units</caption>
      <thead>
        <tr>
          <th className="ml-unit-group__cell" scope="col">Size</th>
          <th className="ml-unit-group__cell" scope="col">Income Limit</th>
          <th className="ml-unit-group__cell" scope="col">Price</th>
        </tr>
      </thead>
      <tbody>
        {
          units.map( ( unit ) => <Unit key={ unit.id } unit={ unit } percentageOfIncomeExplanationId={ percentageOfIncomeExplanationId } /> )
        }
      </tbody>
    </table>
  );
}

UnitGroup.propTypes = {
  "units": PropTypes.arrayOf( unitObject ),
  "percentageOfIncomeExplanationId": PropTypes.string,
};

export default UnitGroup;
