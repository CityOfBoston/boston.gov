import '@babel/polyfill';
import '__mocks__/matchMedia';
import '@testing-library/jest-dom/extend-expect';

import React from 'react';
import { render, act, fireEvent } from '@testing-library/react';
import { LocalStorageMock } from '@react-mock/localstorage';
import { MemoryRouter } from 'react-router-dom';

import Routes from '@components/Routes';

// import AmiEstimator from './index';

describe( 'AmiEstimator', () => {
  beforeAll( () => {
    jest.useFakeTimers();
  } );

  afterAll( () => {
    jest.useRealTimers();
  } );

  it( 'Renders', () => {
    const { getByText } = render(
      <MemoryRouter initialEntries={['/metrolist/ami-estimator']} initialIndex={0}>
        <Routes />
      </MemoryRouter>,
    );
    getByText( /Step [0-9]+ of [0-9]+/ );
  } );

  it( 'Warns about missing input', () => {
    const { getByText, getByTestId } = render(
      <MemoryRouter initialEntries={['/metrolist/ami-estimator']} initialIndex={0}>
        <Routes />
      </MemoryRouter>,
    );
    const nextButton = getByText( 'Next' );

    act( () => {
      fireEvent.click( nextButton );
    } );

    const errorMessage = getByTestId( 'ml-form-error-message' );

    expect( errorMessage ).toHaveTextContent( 'Please specify how many people live in your household.' );
  } );

  it( 'Navigates between steps', async () => {
    const {
      getByText,
      queryByTestId,
      queryByLabelText,
      queryByPlaceholderText,
    } = render(
      <MemoryRouter initialEntries={['/metrolist/ami-estimator']} initialIndex={0}>
        <Routes />
      </MemoryRouter>,
    );
    const nextButton = getByText( 'Next' );
    const previousButton = getByText( 'Back' );
    const firstStep = () => queryByTestId( 'ml-ami-estimator__household-size' );
    const firstStepInput = () => queryByLabelText( '4' );
    const firstStepTitle = 'AMI Estimator: Household Size';

    const secondStep = () => queryByTestId( 'ml-ami-estimator__household-income' );
    const secondStepInput = () => queryByPlaceholderText( '$0.00' );
    const secondStepTitle = 'AMI Estimator: Household Income';

    const thirdStep = () => queryByTestId( 'ml-ami-estimator__disclosure' );
    const thirdStepInput = () => queryByLabelText( 'I have read and understand the above statement.' );
    const thirdStepTitle = 'AMI Estimator: Disclosure';

    const fourthStep = () => queryByTestId( 'ml-ami-estimator__result' );
    const fourthStepTitle = 'AMI Estimator: Result';

    /* Should be on Step 1 - Household Size */
    expect( firstStep() ).toBeInTheDocument();
    expect( secondStep() ).not.toBeInTheDocument();
    expect( thirdStep() ).not.toBeInTheDocument();
    expect( fourthStep() ).not.toBeInTheDocument();
    expect( document.title ).toMatch( new RegExp( `.*${firstStepTitle}.*` ) );

    act( () => {
      fireEvent.click( firstStepInput() );
    } );
    act( () => {
      fireEvent.click( nextButton );
      jest.advanceTimersByTime( 1000 );
    } );

    /* Should be on Step 2 - Household Income */
    expect( firstStep() ).not.toBeInTheDocument();
    expect( secondStep() ).toBeInTheDocument();
    expect( thirdStep() ).not.toBeInTheDocument();
    expect( fourthStep() ).not.toBeInTheDocument();
    expect( document.title ).toMatch( new RegExp( `.*${secondStepTitle}.*` ) );

    act( () => {
      fireEvent.change( secondStepInput(), { "target": { "value": "500000" } } ); // Doesn’t work
    } );
    act( () => {
      // fireEvent.click( getByLabelText( 'income rate', { "selector": 'input[value="Monthly"]' } ) );
      fireEvent.click( nextButton );
      jest.advanceTimersByTime( 1000 );
    } );

    /* Should be on Step 3 - Disclosure */
    expect( firstStep() ).not.toBeInTheDocument();
    expect( secondStep() ).not.toBeInTheDocument();
    expect( thirdStep() ).toBeInTheDocument();
    expect( fourthStep() ).not.toBeInTheDocument();
    expect( document.title ).toMatch( new RegExp( `.*${thirdStepTitle}.*` ) );

    act( () => {
      fireEvent.click( thirdStepInput() );
    } );
    act( () => {
      fireEvent.click( nextButton );
      jest.advanceTimersByTime( 1000 );
    } );

    /* Should be on Step 4 - Result */
    expect( firstStep() ).not.toBeInTheDocument();
    expect( secondStep() ).not.toBeInTheDocument();
    expect( thirdStep() ).not.toBeInTheDocument();
    expect( fourthStep() ).toBeInTheDocument();
    expect( document.title ).toMatch( new RegExp( `.*${fourthStepTitle}.*` ) );

    act( () => {
      fireEvent.click( previousButton );
      jest.advanceTimersByTime( 1000 );
    } );

    /* Should be back on Step 3 */
    expect( firstStep() ).not.toBeInTheDocument();
    expect( secondStep() ).not.toBeInTheDocument();
    expect( thirdStep() ).toBeInTheDocument();
    expect( fourthStep() ).not.toBeInTheDocument();

    act( () => {
      fireEvent.click( previousButton );
      jest.advanceTimersByTime( 1000 );
    } );

    /* Should be back on Step 2 */
    expect( firstStep() ).not.toBeInTheDocument();
    expect( secondStep() ).toBeInTheDocument();
    expect( thirdStep() ).not.toBeInTheDocument();
    expect( fourthStep() ).not.toBeInTheDocument();

    act( () => {
      fireEvent.click( previousButton );
      jest.advanceTimersByTime( 1000 );
    } );

    /* Should be back on Step 1 */
    expect( firstStep() ).toBeInTheDocument();
    expect( secondStep() ).not.toBeInTheDocument();
    expect( thirdStep() ).not.toBeInTheDocument();
    expect( fourthStep() ).not.toBeInTheDocument();
  } );

  it( 'Stores household income for use on other pages', () => {
    const { getByRole } = render(
      <LocalStorageMock items={ {} }>
        <MemoryRouter initialEntries={['/metrolist/ami-estimator/household-income']} initialIndex={0}>
          <Routes />
        </MemoryRouter>
      </LocalStorageMock>,
    );

    fireEvent.change(
      getByRole( 'textbox' ),
      {
        "target": {
          "value": "$5,000.00",
        },
      },
    );

    expect( localStorage.getItem( 'householdIncome' ) ).toBe( '$5,000.00' );
  } );
} );
