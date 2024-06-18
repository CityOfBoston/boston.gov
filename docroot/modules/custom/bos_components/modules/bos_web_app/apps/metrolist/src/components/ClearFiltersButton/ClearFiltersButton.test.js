import '__mocks__/matchMedia';
import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

import ClearFiltersButton from './index';

describe( 'ClearFiltersButton', () => {
  it( 'Renders', () => {
    /*
      "children": PropTypes.node,
      "className": PropTypes.string,
      "clearFilters": PropTypes.func.isRequired,
      "undoClearFilters": PropTypes.func.isRequired,
      "hasInteractedWithFilters": PropTypes.bool.isRequired,
      "showClearFiltersInitially": PropTypes.bool,
      "lastInteractedWithFilters": PropTypes.number,
    */

    render(
      <ClearFiltersButton
        clearFilters={ () => {} }
        undoClearFilters={ () => {} }
        // showClearFiltersInitially={ false }
        hasInteractedWithFilters={ false }
        lastInteractedWithFilters={ Date.now() }
      />,
    );
  } );
} );
