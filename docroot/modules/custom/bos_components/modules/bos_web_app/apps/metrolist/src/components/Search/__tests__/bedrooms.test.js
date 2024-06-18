import '@babel/polyfill';
import '__mocks__/matchMedia';
import {
  minimalHomeDefinition,
  studioUnit,
  oneBedroomUnit,
  twoBedroomUnit,
  threeBedroomUnit,
  aboveThreeBedroomUnit,
} from '__mocks__/homes';
import { getNoFiltersApplied } from '__mocks__/filters';
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

const bedroomsTest = () => {
  beforeAll( () => {
    jest.useFakeTimers();
  } );

  afterAll( () => {
    jest.useRealTimers();
  } );

  it( 'Filters by unit size', () => {
    const homeWithUnitsForEveryBedroomSize = {
      ...minimalHomeDefinition,
      "id": generateRandomNumberString(),
      "units": [
        studioUnit,
        oneBedroomUnit,
        twoBedroomUnit,
        threeBedroomUnit,
        aboveThreeBedroomUnit,
      ],
    };
    const homesToFilter = [homeWithUnitsForEveryBedroomSize];
    const {
      getByLabelText, getByTestId, queryByTestId,
    } = render(
      <MemoryRouter initialEntries={ ['/metrolist/search'] } initialIndex={ 0 }>
        <Search
          homes={ homesToFilter }
          filters={ getNoFiltersApplied() }
        />
      </MemoryRouter>,
    );
    const zeroBedroomInput = getByLabelText( '0' );
    const twoBedroomInput = getByLabelText( '2' );
    const aboveThreeBedroomInput = getByLabelText( '3+' );

    act( () => {
      jest.advanceTimersByTime( 1500 );
      fireEvent.click( zeroBedroomInput );
      // fireEvent.change( zeroBedroomInput, { "target": { "value": true } } );
    } );

    getByTestId( '0br' );
    expect( queryByTestId( '1br' ) ).not.toBeInTheDocument();
    expect( queryByTestId( '2br' ) ).not.toBeInTheDocument();
    expect( queryByTestId( '3br' ) ).not.toBeInTheDocument();
    expect( queryByTestId( '3+br' ) ).not.toBeInTheDocument();

    act( () => {
      fireEvent.click( zeroBedroomInput );
      fireEvent.click( twoBedroomInput );
      fireEvent.click( aboveThreeBedroomInput );
    } );

    expect( queryByTestId( '0br' ) ).not.toBeInTheDocument();
    expect( queryByTestId( '1br' ) ).not.toBeInTheDocument();
    getByTestId( '2br' );
    getByTestId( '3br' );
    getByTestId( '3+br' );
  } );
};

describe( 'Bedrooms', bedroomsTest );

export default bedroomsTest;
