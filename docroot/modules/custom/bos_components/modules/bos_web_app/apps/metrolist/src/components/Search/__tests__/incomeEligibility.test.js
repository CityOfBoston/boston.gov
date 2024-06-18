import '@babel/polyfill';
import '__mocks__/matchMedia';
import {
  minimalHomeDefinition,
  studioUnit,
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

import Search from '@components/Search';

const incomeEligibilityTest = () => {
  beforeAll( () => {
    jest.useFakeTimers();
  } );

  afterAll( () => {
    jest.useRealTimers();
  } );

  it( 'Filters by AMI Percentage', () => {
    // const amiBetweenEightyAndOneHundred = {
    //   ...getNoFiltersApplied(),
    //   "amiQualification": {
    //     "lowerBound": 80,
    //     "upperBound": 100,
    //   },
    // };
    const homeWithAmiAboveUpperBound = {
      ...minimalHomeDefinition,
      "id": generateRandomNumberString(),
      "title": "Home with AMI Above Upper Bound",
      "offer": "rent",
      "units": [{
        ...studioUnit,
        "id": "studio-above-upper-bound",
        "amiQualification": 110,
      }],
    };
    const homeWithAmiWithinBounds = {
      ...minimalHomeDefinition,
      "id": generateRandomNumberString(),
      "title": "Home with AMI Within Bounds",
      "offer": "rent",
      "units": [{
        ...studioUnit,
        "id": "studio-within-bounds",
        "amiQualification": 90,
      }],
    };
    const homeWithAmiBelowLowerBound = {
      ...minimalHomeDefinition,
      "id": generateRandomNumberString(),
      "title": "Home with AMI Below Lower Bound",
      "offer": "rent",
      "units": [{
        ...studioUnit,
        "id": "studio-below-lower-bound",
        "amiQualification": 70,
      }],
    };
    const homesToFilter = [homeWithAmiAboveUpperBound, homeWithAmiWithinBounds, homeWithAmiBelowLowerBound];
    const {
      getByTestId, queryByTestId, getByText,
    } = render(
      <MemoryRouter initialEntries={ ['/metrolist/search'] } initialIndex={ 0 }>
        <Search
          homes={ homesToFilter }
          // filters={ getNoFiltersApplied() }
        />
      </MemoryRouter>,
    );
    const lowerBoundInput = getByTestId( 'amiQualificationLowerBound' );
    const upperBoundInput = getByTestId( 'amiQualificationUpperBound' );

    act( () => {
      jest.advanceTimersByTime( 1500 );
      fireEvent.change( lowerBoundInput, { "target": { "value": 80 } } );
      fireEvent.change( upperBoundInput, { "target": { "value": 100 } } );
    } );

    getByText( '80%', { "selector": "output" } );
    getByText( '100%', { "selector": "output" } );

    getByTestId( homeWithAmiWithinBounds.units[0].id );
    expect( queryByTestId( homeWithAmiAboveUpperBound.units[0].id ) ).not.toBeInTheDocument();
    expect( queryByTestId( homeWithAmiBelowLowerBound.units[0].id ) ).not.toBeInTheDocument();
  } );
};

describe( 'Income Eligibility', incomeEligibilityTest );

export default incomeEligibilityTest;
