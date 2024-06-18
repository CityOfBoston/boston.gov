import React from 'react';
import { render } from '@testing-library/react';
import AppHeader from './index';

describe( 'AppHeader', () => {
  it( 'Renders', () => {
    render( <AppHeader /> );
  } );
} );
