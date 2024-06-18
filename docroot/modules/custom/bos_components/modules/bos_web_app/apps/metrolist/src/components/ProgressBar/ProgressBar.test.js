import React from 'react';
import { render } from '@testing-library/react';
import ProgressBar from './index';

describe( 'ProgressBar', () => {
  it( 'Renders', () => {
    render( <ProgressBar /> );
  } );
} );
