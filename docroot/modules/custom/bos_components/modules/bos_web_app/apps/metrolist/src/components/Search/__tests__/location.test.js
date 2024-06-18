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
  waitFor,
} from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';
import { generateRandomNumberString } from '@util/strings';
import { MemoryRouter } from 'react-router-dom';
import { act } from 'react-dom/test-utils';

import Search from '@components/Search';

const locationTest = () => {
  beforeAll( () => {
    jest.useFakeTimers();
  } );

  afterAll( () => {
    jest.useRealTimers();
  } );

  const dorchesterHome = {
    ...minimalHomeDefinition,
    "id": generateRandomNumberString(),
    "title": "Dorchester Home",
    "city": "Boston",
    "neighborhood": "Dorchester",
    "cardinalDirection": null,
  };
  const southieHome = {
    ...minimalHomeDefinition,
    "id": generateRandomNumberString(),
    "title": "Southie Home",
    "city": "Boston",
    "neighborhood": "South Boston",
    "cardinalDirection": null,
  };
  const cambridgeHome = {
    ...minimalHomeDefinition,
    "id": generateRandomNumberString(),
    "title": "Cambridge Home",
    "city": "Cambridge",
    "neighborhood": null,
    "cardinalDirection": "west",
  };

  it( 'Filters results to only homes within Boston', () => {
    const homesToFilter = [dorchesterHome, cambridgeHome];
    const { getByLabelText, queryByText } = render(
      <MemoryRouter initialEntries={['/metrolist/search']} initialIndex={0}>
        <Search
          homes={ homesToFilter }
          filters={ getNoFiltersApplied() }
        />
      </MemoryRouter>,
    );
    const bostonInput = getByLabelText( 'Boston', { "selector": "input" } );
    act( () => {
      fireEvent.click( bostonInput );
    } );
    const homeToBeFilteredOut = queryByText( cambridgeHome.title );

    expect( bostonInput ).toBeChecked();
    expect( homeToBeFilteredOut ).not.toBeInTheDocument();
  } );

  it( 'Filters results to only homes outside Boston', () => {
    const homesToFilter = [dorchesterHome, cambridgeHome];
    const { getByLabelText, queryByText } = render(
      <MemoryRouter initialEntries={['/metrolist/search']} initialIndex={0}>
        <Search
          homes={ homesToFilter }
          filters={ getNoFiltersApplied() }
        />
      </MemoryRouter>,
    );
    const beyondBostonInput = getByLabelText( 'Beyond Boston', { "selector": "input" } );
    act( () => {
      fireEvent.click( beyondBostonInput );
    } );
    const homeToBeFilteredOut = queryByText( dorchesterHome.title );

    expect( beyondBostonInput ).toBeChecked();
    expect( homeToBeFilteredOut ).not.toBeInTheDocument();
  } );

  it( 'Sets all neighborhood checkboxes appropriately when the “Boston” checkbox is toggled', async () => {
    const homesToFilter = [dorchesterHome, southieHome];
    const { getByLabelText } = render(
      <MemoryRouter initialEntries={['/metrolist/search']} initialIndex={0}>
        <Search
          homes={ homesToFilter }
          filters={ getNoFiltersApplied() }
        />
      </MemoryRouter>,
    );
    const bostonInput = getByLabelText( 'Boston', { "selector": "input" } );
    const dotInput = getByLabelText( /Dorchester \(.*\)/, { "selector": "input" } );
    const southieInput = getByLabelText( /South Boston \(.*\)/, { "selector": "input" } );

    // For some reason the first fireEvent has to be a change,
    // and the second fireEvent has to be a click for the test to pass.
    // Makes no sense whatsoever.
    act( () => {
      // fireEvent.click( bostonInput );
      fireEvent.change( bostonInput, { "target": { "checked": true } } );
    } );

    expect( bostonInput ).toBeChecked();
    expect( dotInput ).toBeChecked();
    expect( southieInput ).toBeChecked();

    act( () => {
      fireEvent.click( bostonInput );
      // fireEvent.change( bostonInput, { "target": { "checked": false } } );
    } );

    expect( bostonInput ).not.toBeChecked();
    expect( dotInput ).not.toBeChecked();
    expect( southieInput ).not.toBeChecked();
  } );

  it( 'Sets all cardinal direction checkboxes appropriately when the “Beyond Boston” checkbox is toggled', async () => {
    const { getByLabelText } = render(
      <MemoryRouter initialEntries={['/metrolist/search']} initialIndex={0}>
        <Search filters={ getNoFiltersApplied() } />
      </MemoryRouter>,
    );
    const beyondBostonInput = getByLabelText( 'Beyond Boston',
      { "selector": "input" } );
    let westInput;
    let southInput;

    await act( async () => {
      westInput = await waitFor( () => getByLabelText( /West of Boston \(.*\)/, { "selector": "input" } ) );
      southInput = await waitFor( () => getByLabelText( /South of Boston \(.*\)/, { "selector": "input" } ) );

      // For some reason this fireEvent has to go inside act() or there is a race condition and the test fails,
      // even though it works outside of act() in the previous test
      fireEvent.click( beyondBostonInput );
    } );

    expect( beyondBostonInput ).toBeChecked();
    expect( westInput ).toBeChecked();
    expect( southInput ).toBeChecked();

    act( () => {
      fireEvent.click( beyondBostonInput );
    } );

    expect( beyondBostonInput ).not.toBeChecked();
    expect( westInput ).not.toBeChecked();
    expect( southInput ).not.toBeChecked();
  } );
};

describe( 'Location', locationTest );

export default locationTest;
