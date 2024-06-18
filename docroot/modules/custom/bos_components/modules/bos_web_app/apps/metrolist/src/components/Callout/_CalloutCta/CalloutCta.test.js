import '__mocks__/matchMedia';
import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

import CalloutCta from './index';

describe( 'CalloutCta', () => {
  it( 'Renders', () => {
    render( <CalloutCta /> );
  } );
} );
