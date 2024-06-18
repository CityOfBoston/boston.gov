import '__mocks__/matchMedia';
import React from 'react';
import { render } from '@testing-library/react';
// import '@testing-library/jest-dom/extend-expect';
import { MemoryRouter } from 'react-router-dom';

import Routes from './index';

describe( 'Routes', () => {
  it( 'Renders', () => {
    render(
      <MemoryRouter>
        <Routes />
      </MemoryRouter>,
    );
  } );
} );
