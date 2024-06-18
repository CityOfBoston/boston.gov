import React from 'react';
import { render } from '@testing-library/react';
import Checkbox from './index';

describe( 'Checkbox', () => {
  it( 'Renders', () => {
    render( <Checkbox>Click me</Checkbox> );
  } );
} );
