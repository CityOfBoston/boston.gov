import '__mocks__/matchMedia';
import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';
import { MemoryRouter } from 'react-router-dom';

import ScrollToTop from './index';

describe( 'ScrollToTop', () => {
  globalThis.scrollTo = jest.fn();

  it( 'Renders', () => {
    const spy = jest.spyOn( globalThis, 'scrollTo' );

    render(
      <MemoryRouter>
        <ScrollToTop />
      </MemoryRouter>,
    );

    expect( spy ).toHaveBeenCalledTimes( 1 );
  } );
} );
