import React from 'react';
import { render } from '@testing-library/react';
import { studioUnit } from '__mocks__/homes';
import Unit from './index';

describe( 'Unit', () => {
  it( 'Renders', () => {
    render(
      <table>
        <tbody>
          <Unit unit={ studioUnit } />
        </tbody>
      </table>,
    );
  } );
} );
