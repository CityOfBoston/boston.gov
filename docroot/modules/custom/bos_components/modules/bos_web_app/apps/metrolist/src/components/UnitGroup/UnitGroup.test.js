import React from 'react';
import { render } from '@testing-library/react';
import { studioUnit } from '__mocks__/homes';
import UnitGroup from './index';

describe( 'UnitGroup', () => {
  it( 'Renders', () => {
    render( <UnitGroup units={ [studioUnit] } /> );
  } );
} );
