import '@babel/polyfill';
// import '__mocks__/matchMedia';
import '@testing-library/jest-dom/extend-expect';

import React from 'react';
import { render } from '@testing-library/react';

import AmiEstimatorInputSummary from './index';

describe( 'AmiEstimatorResult', () => {
  it( 'Displays the entered form data', () => {
    const formData = {
      "householdSize": {
        "value": "4",
      },
      "householdIncome": {
        "value": "$5,000.00",
      },
      "incomeRate": {
        "value": "monthly",
      },
    };
    const { getByText } = render( <AmiEstimatorInputSummary formData={ formData } /> );
    const householdIncomeSansTrailingDecimalPoints = formData.householdIncome.value.substring( 0, formData.householdIncome.value.length - 3 );

    getByText( '4 people' );
    getByText( `${householdIncomeSansTrailingDecimalPoints}/month` );
  } );

  it( 'Prints “1 person” if Household Size is 1', () => {
    const formData = {
      "householdSize": {
        "value": "1",
      },
      "householdIncome": {
        "value": "$5,000.00",
      },
      "incomeRate": {
        "value": "monthly",
      },
    };
    const { getByText } = render( <AmiEstimatorInputSummary formData={ formData } /> );

    getByText( '1 person' );
  } );

  it( 'Prints “0 people” if Household Size is not specified', () => {
    const formData = {
      "householdSize": {
        "value": null,
      },
      "householdIncome": {
        "value": "$5,000.00",
      },
      "incomeRate": {
        "value": "monthly",
      },
    };
    const { getByText } = render( <AmiEstimatorInputSummary formData={ formData } /> );

    getByText( '0 people' );
  } );
} );
