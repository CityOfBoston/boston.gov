import React from 'react';
import {
  render,
  // fireEvent,
} from '@testing-library/react';
// import {
//   minimalHomeDefinition,
//   studioUnit,
//   oneBedroomUnit,
//   twoBedroomUnit,
//   threeBedroomUnit,
//   aboveThreeBedroomUnit,
// } from '__mocks__/homes';
// import { getNoFiltersApplied } from '__mocks__/filters';
// import { generateRandomNumberString } from '@util/strings';
// import { LocalStorageMock } from '@react-mock/localstorage';
// import { MemoryRouter } from 'react-router-dom';
// import { act } from 'react-dom/test-utils';
import '@testing-library/jest-dom/extend-expect';

import ResultsPanel from './index';

describe( 'Results Panel', () => {
  it( 'Renders', () => {
    render( <ResultsPanel /> );
  } );
} );
