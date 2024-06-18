import '__mocks__/matchMedia';
import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

import CalloutIcon from './index';

describe( 'CalloutIcon', () => {
  it( 'Renders', () => {
    render( <CalloutIcon /> );
  } );
} );
