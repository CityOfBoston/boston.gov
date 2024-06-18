import '__mocks__/matchMedia';
import React from 'react';
import { render } from '@testing-library/react';
import { minimalHomeDefinition } from '__mocks__/homes';
import { getNoFiltersApplied } from '__mocks__/filters';
import { generateRandomNumberString } from '@util/strings';
import Home from './index';

describe( 'Home', () => {
  it( 'Renders', () => {
    render(
      <Home
        home={ {
          ...minimalHomeDefinition,
          "id": generateRandomNumberString(),
        } }
        filters={ getNoFiltersApplied() }
      />,
    );
  } );
} );
