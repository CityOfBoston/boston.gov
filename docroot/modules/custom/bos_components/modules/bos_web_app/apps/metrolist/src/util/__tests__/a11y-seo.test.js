import React, { useEffect } from 'react';
import { render, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

import * as a11ySeo from '../a11y-seo';

describe( 'Accessibility/SEO Utilities', () => {
  describe( 'updatePageTitle()', () => {
    it( 'Updates document.title', () => {
      const DummyComponent = () => {
        useEffect( () => a11ySeo.updatePageTitle( 'Page Title', 'Section Title' ) );

        return null;
      };

      render( <DummyComponent /> );

      expect( document.title ).toMatch( /Section Title: Page Title/ );
    } );
  } );

  describe( 'handlePseudoButtonKeyDown()', () => {
    let handleClick;
    let PseudoButton;

    it( 'Prevents default keypress action', () => {
      handleClick = jest.fn();

      let mockEvent;

      PseudoButton = () => ( // eslint-disable-line react/display-name
        <div
          role="button"
          onKeyDown={ ( event ) => {
            mockEvent = { ...event, "preventDefault": jest.fn() };
            // jest.spyOn( mockEvent, 'preventDefault' );
            a11ySeo.handlePseudoButtonKeyDown( mockEvent );
          } }
          onClick={ handleClick }
          className="pseudo-button"
        >Pseudo-button</div>
      );

      const { getByRole } = render( <PseudoButton /> );

      fireEvent.keyDown(
        getByRole( 'button' ),
        {
          "key": "Enter",
          "code": "Enter",
        },
      );

      expect( mockEvent.preventDefault ).toHaveBeenCalledTimes( 1 );
    } );

    describe( 'Responds to keypresses', () => {
      beforeEach( () => {
        handleClick = jest.fn();

        PseudoButton = () => ( // eslint-disable-line react/display-name
          <div
            role="button"
            onKeyDown={ ( event ) => a11ySeo.handlePseudoButtonKeyDown( event, true ) }
            onClick={ handleClick }
            className="pseudo-button"
          >Pseudo-button</div>
        );
      } );

      test( 'Enter', () => {
        const { getByRole } = render( <PseudoButton /> );

        fireEvent.keyDown(
          getByRole( 'button' ),
          {
            "key": "Enter",
            "code": "Enter",
          },
        );

        expect( handleClick ).toHaveBeenCalledTimes( 1 );
      } );

      test( 'Space', () => {
        const { getByRole } = render( <PseudoButton /> );

        fireEvent.keyDown(
          getByRole( 'button' ),
          {
            "key": " ",
            "code": "Space",
          },
        );

        expect( handleClick ).toHaveBeenCalledTimes( 1 );
      } );

      test( 'Spacebar (IE11 compat)', () => {
        const { getByRole } = render( <PseudoButton /> );

        fireEvent.keyDown(
          getByRole( 'button' ),
          {
            "key": "Spacebar",
            "code": "Spacebar",
          },
        );

        expect( handleClick ).toHaveBeenCalledTimes( 1 );
      } );
    } );
  } );
} );
