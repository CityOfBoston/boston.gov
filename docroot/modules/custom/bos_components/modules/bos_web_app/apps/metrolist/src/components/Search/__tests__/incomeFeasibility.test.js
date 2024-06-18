import '@babel/polyfill';
import '__mocks__/matchMedia';
import {
  minimalHomeDefinition,
  oneBedroomUnit,
  twoBedroomUnit,
} from '__mocks__/homes';
import React from 'react';
import {
  render,
  fireEvent,
} from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';
import { generateRandomNumberString } from '@util/strings';
import { MemoryRouter } from 'react-router-dom';
import { act } from 'react-dom/test-utils';
import { LocalStorageMock } from '@react-mock/localstorage';

import Search from '@components/Search';

const incomeFeasibilityTest = () => {
  beforeAll( () => {
    jest.useFakeTimers();
  } );

  afterAll( () => {
    jest.useRealTimers();
  } );

  it( 'Hides income-restricted homes with limits higher than one’s household income', () => {
    const homesToFilter = [
      {
        ...minimalHomeDefinition,
        "id": generateRandomNumberString(),
        "units": [
          {
            ...oneBedroomUnit,
            "incomeQualification": 65000,
          },
          {
            ...twoBedroomUnit,
            "incomeQualification": null,
          },
        ],
      },
    ];

    const { queryByTestId, getByLabelText } = render(
      <LocalStorageMock items={ { "householdIncome": "$5,000.00", "incomeRate": "monthly" } }>
        <MemoryRouter initialEntries={ ['/metrolist/search'] } initialIndex={ 0 }>
          <Search homes={ homesToFilter } />
        </MemoryRouter>
      </LocalStorageMock>,
    );
    const incomeRestrictionFilterToggle = getByLabelText( /Hide homes that require a household income over \$[0-9,]+\/(mo|yr)\./, { "selector": "input" } );

    act( () => {
      jest.advanceTimersByTime( 1500 );
      fireEvent.click( incomeRestrictionFilterToggle );
    } );

    expect( queryByTestId( '1br' ) ).not.toBeInTheDocument();
    expect( queryByTestId( '2br' ) ).toBeInTheDocument();

    act( () => {
      fireEvent.click( incomeRestrictionFilterToggle );
    } );

    expect( queryByTestId( '1br' ) ).toBeInTheDocument();
    expect( queryByTestId( '2br' ) ).toBeInTheDocument();
  } );
};

describe( 'Income Feasibility', incomeFeasibilityTest );

export default incomeFeasibilityTest;
