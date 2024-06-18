import '@babel/polyfill';
import '__mocks__/matchMedia';
import {
  minimalHomeDefinition,
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

const offerTest = () => {
  beforeAll( () => {
    jest.useFakeTimers();
  } );

  afterAll( () => {
    jest.useRealTimers();
  } );

  const homeToRent = {
    ...minimalHomeDefinition,
    "id": generateRandomNumberString(),
    "title": "Home 1",
    "offer": "rent",
  };
  const homeToBuy = {
    ...minimalHomeDefinition,
    "id": generateRandomNumberString(),
    "title": "Home 2",
    "offer": "sale",
  };

  it( 'Filters results to only homes for rent', () => {
    const homesToFilter = [homeToRent, homeToBuy];
    const { getByLabelText, queryByText } = render(
      <MemoryRouter initialEntries={['/metrolist/search']} initialIndex={0}>
        <Search
          homes={ homesToFilter }
          filters={
            {
              ...getNoFiltersApplied(),
              "offer": {
                "rent": false,
                "sale": false,
              },
            }
          }
        />
      </MemoryRouter>,
    );
    const forRentInput = getByLabelText( /For Rent \(.*\)/, { "selector": "input" } );

    act( () => {
      jest.advanceTimersByTime( 1500 );
      fireEvent.click( forRentInput );
    } );

    const homeToBeFilteredOut = queryByText( homeToBuy.title );

    expect( forRentInput ).toBeChecked();
    expect( homeToBeFilteredOut ).not.toBeInTheDocument();
  } );


  it( 'Filters results to only homes for sale', () => {
    const homesToFilter = [homeToRent, homeToBuy];
    const { getByLabelText, queryByText } = render(
      <MemoryRouter initialEntries={['/metrolist/search']} initialIndex={0}>
        <Search
          homes={ homesToFilter }
          filters={
            {
              ...getNoFiltersApplied(),
              "offer": {
                "rent": false,
                "sale": false,
              },
            }
          }
        />
      </MemoryRouter>,
    );
    const forSaleInput = getByLabelText( /For Sale \(.*\)/, { "selector": "input" } );
    act( () => {
      fireEvent.click( forSaleInput );
    } );
    const homeToBeFilteredOut = queryByText( homeToRent.title );

    expect( forSaleInput ).toBeChecked();
    expect( homeToBeFilteredOut ).not.toBeInTheDocument();
  } );
};

describe( 'Offer', offerTest );

export default offerTest;
