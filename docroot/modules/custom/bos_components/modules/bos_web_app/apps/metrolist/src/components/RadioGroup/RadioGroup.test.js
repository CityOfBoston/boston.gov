import React from 'react';
import { render } from '@testing-library/react';
import RadioGroup from './index';

describe( 'RadioGroup', () => {
  it( 'Renders', () => {
    render( <RadioGroup values="1,2,3,4,5" /> );
  } );
} );
