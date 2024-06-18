import '__mocks__/matchMedia';
import React from 'react';
import { render } from '@testing-library/react';
import Checkbox from '@components/Checkbox';
import FilterGroup from './index';

describe( 'FilterGroup', () => {
  it( 'Renders', () => {
    render(
      <FilterGroup>
        <Checkbox />
      </FilterGroup>,
    );
  } );
} );
