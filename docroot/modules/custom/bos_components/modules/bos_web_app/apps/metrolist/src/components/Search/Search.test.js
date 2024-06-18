import '@babel/polyfill';
import '__mocks__/matchMedia';
import React from 'react';
import {
  render,
  cleanup,
  waitFor,
  getAllByRole,
} from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';
import { MemoryRouter } from 'react-router-dom';
import { rest } from 'msw';
import { setupServer } from 'msw/node';
import { act } from 'react-dom/test-utils';
import { getDevelopmentsApiEndpoint } from '@util/dev';
import mockApiResponse from '__mocks__/developmentsApiResponse.json';

import Search from '@components/Search';

// Mock Developments API response
const server = setupServer(
  rest.get(
    getDevelopmentsApiEndpoint(),
    ( request, response, context ) => response(
      context.json( mockApiResponse ),
    ),
  ),
);

describe( 'Search', () => {
  beforeAll( () => {
    jest.useFakeTimers();
    server.listen();
  } );

  afterEach( () => {
    server.resetHandlers();
    cleanup();
  } );

  afterAll( () => {
    server.close();
    jest.useRealTimers();
  } );

  it( 'Renders', async () => {
    const { getByTestId } = render(
      <MemoryRouter initialEntries={['/metrolist/search']} initialIndex={0}>
        <Search />
      </MemoryRouter>,
    );
    const FiltersPanel = getByTestId( 'ml-filters-panel' );
    const ResultsPanel = getByTestId( 'ml-results-panel' );

    await act( async () => {
      jest.advanceTimersByTime( 1500 );
      await waitFor( () => getAllByRole( ResultsPanel, 'article' ) );
    } );

    expect( FiltersPanel ).toBeInTheDocument();
    expect( ResultsPanel ).toBeInTheDocument();
  } );
} );
