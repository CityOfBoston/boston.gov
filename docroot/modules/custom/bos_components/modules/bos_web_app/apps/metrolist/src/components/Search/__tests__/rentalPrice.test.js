import '@babel/polyfill';
import '__mocks__/matchMedia';
import {
  minimalHomeDefinition,
  oneBedroomUnit,
  threeBedroomUnit,
} from '__mocks__/homes';
import { getNoFiltersApplied } from '__mocks__/filters';
import React from 'react';
import {
  render,
  fireEvent,
  waitFor,
  getAllByRole,
} from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';
import { generateRandomNumberString } from '@util/strings';
import { MemoryRouter } from 'react-router-dom';
import { act } from 'react-dom/test-utils';

import Search from '@components/Search';

const rentalPriceTest = () => {
  beforeAll( () => {
    jest.useFakeTimers();
  } );

  afterAll( () => {
    jest.useRealTimers();
  } );

  const homeWithUnitWithinPriceRange = {
    ...minimalHomeDefinition,
    "id": generateRandomNumberString(),
    "title": `$${oneBedroomUnit.price} - Affordable`,
    "offer": "rent",
    "units": [
      oneBedroomUnit,
    ],
  };
  const homeWithUnitOutsidePriceRange = {
    ...minimalHomeDefinition,
    "id": generateRandomNumberString(),
    "title": `$${threeBedroomUnit.price} - Unaffordable`,
    "offer": "rent",
    "units": [
      threeBedroomUnit,
    ],
  };

  it( 'Filters results to only homes with units in the specified price range', async () => {
    const homesToFilter = [homeWithUnitWithinPriceRange, homeWithUnitOutsidePriceRange];
    const filtersToApply = { ...getNoFiltersApplied() };
    const { getByText, queryByText, getByTestId } = render(
      <MemoryRouter initialEntries={['/metrolist/search']} initialIndex={0}>
        <Search
          homes={ homesToFilter }
          filters={ filtersToApply }
        />
      </MemoryRouter>,
    );
    const ResultsPanel = getByTestId( 'ml-results-panel' );
    const lowerBoundInput = getByTestId( 'rentalPriceLowerBound' );
    const upperBoundInput = getByTestId( 'rentalPriceUpperBound' );

    const affordableHome = () => queryByText( homeWithUnitWithinPriceRange.title );
    const unaffordableHome = () => queryByText( homeWithUnitOutsidePriceRange.title );

    await act( async () => {
      jest.advanceTimersByTime( 1500 );
      await waitFor( () => getAllByRole( ResultsPanel, 'article' ) );
    } );

    act( () => {
      fireEvent.change( lowerBoundInput, { "target": { "value": "1000" } } );
      fireEvent.change( upperBoundInput, { "target": { "value": "2000" } } );
    } );

    getByText( '$1,000' );
    getByText( '$2,000' );

    expect( affordableHome() ).toBeInTheDocument();
    expect( unaffordableHome() ).not.toBeInTheDocument();

    act( () => {
      fireEvent.change( lowerBoundInput, { "target": { "value": "2000" } } );
      fireEvent.change( upperBoundInput, { "target": { "value": "3000" } } );
    } );

    getByText( '$2,000' );
    getByText( '$3,000' );

    expect( affordableHome() ).not.toBeInTheDocument();
    expect( unaffordableHome() ).toBeInTheDocument();
  } );
};

describe( 'Rental Price', rentalPriceTest );

export default rentalPriceTest;
