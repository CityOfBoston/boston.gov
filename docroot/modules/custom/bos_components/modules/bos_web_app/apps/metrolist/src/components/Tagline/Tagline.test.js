import React from 'react';
import { render } from '@testing-library/react';
import Tagline from './index';

describe( 'Tagline', () => {
  it( 'Renders', () => {
    render( <Tagline /> );
  } );
} );
