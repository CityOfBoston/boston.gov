import '__mocks__/matchMedia';
import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

import Required from './index';

describe( 'Required', () => {
  it( 'Renders', () => {
    const { getByText } = render( <Required /> );

    getByText( '*' );
  } );
} );
