import React from 'react';
import { render } from '@testing-library/react';
import HomeInfo from './index';

describe( 'HomeInfo', () => {
  it( 'Renders', () => {
    render( <HomeInfo info={ {} } /> );
  } );
} );
