import '__mocks__/matchMedia';
import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';
import { MemoryRouter } from 'react-router-dom';

import SearchPagination from './index';

describe( 'SearchPagination', () => {
  it( 'Renders', () => {
    render(
      <MemoryRouter>
        <SearchPagination pages={ [] } currentPage={ 0 } />
      </MemoryRouter>,
    );
  } );
} );
