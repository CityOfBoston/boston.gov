import '__mocks__/matchMedia';
import { getNoFiltersApplied } from '__mocks__/filters';
// import { minimalHomeDefinition } from '__mocks__/homes';

import React from 'react';
import {
  render,
  // prettyDOM,
  // fireEvent,
  // createEvent,
} from '@testing-library/react';
// import { act } from 'react-dom/test-utils';
import FiltersPanel from './index';

describe( 'FiltersPanel', () => {
  it( 'Renders', () => {
    render(
      <FiltersPanel
        filters={ getNoFiltersApplied() }
        clearFilters={ () => {} }
        undoClearFilters={ () => {} }
        handleFilterChange={ () => {} }
        updateDrawerHeight={ () => {} }
        listingCounts={ {
          "offer": {
            "rent": 0,
            "sale": 0,
          },
          "location": {
            "city": {
              "boston": 0,
              "beyondBoston": 0,
            },
            "neighborhood": {},
            "cardinalDirection": {
              "west": 0,
              "north": 0,
              "south": 0,
            },
          },
          "rentalPrice": {
            "lowerBound": 0,
            "upperBound": 0,
          },
        } }
        drawerRef={ { "current": null } }
      />,
    );
  } );
} );
