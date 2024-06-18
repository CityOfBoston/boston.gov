import '__mocks__/matchMedia';
import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

import CalloutHeading from './index';

describe( 'CalloutHeading', () => {
  it( 'Renders', () => {
    render( <CalloutHeading /> );
  } );
} );
