import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';
import { LocalStorageMock } from '@react-mock/localstorage';
import { MemoryRouter, useLocation } from 'react-router-dom';

import * as translation from '../translation';

describe( 'Translation Utilities', () => {
  const originalLocation = globalThis.location;
  const mockLocation = {
    "hash": "",
    "host": "translate.googleusercontent.com",
    "hostname": "translate.googleusercontent.com",
    "href": "https://translate.googleusercontent.com/translate_c?depth=1&pto=aue&rurl=translate.google.com&sl=auto&sp=nmt4&tl=ja&u=https://www.boston.gov/metrolist/search&usg=ALkJrhj0_wR4zzdtcpIAb7thW_5nW6hJig",
    "origin": "https://translate.googleusercontent.com",
    "pathname": "/translate_c",
    "port": "",
    "protocol": "https:",
    "search": "?depth=1&pto=aue&rurl=translate.google.com&sl=auto&sp=nmt4&tl=ja&u=https://www.boston.gov/metrolist/search&usg=ALkJrhj0_wR4zzdtcpIAb7thW_5nW6hJig",
  };

  describe( 'isOnGoogleTranslate()', () => {
    beforeEach( () => {
      delete globalThis.location;
    } );

    afterAll( () => {
      globalThis.location = originalLocation;
    } );

    it( 'Returns `true` if the current URL contains a domain or path belonging to Google Translate', () => {
      globalThis.location = { "hostname": "translate.googleusercontent.com" };

      expect( translation.isOnGoogleTranslate() ).toBe( true );

      globalThis.location.hostname = 'translate.google.com';

      expect( translation.isOnGoogleTranslate() ).toBe( true );

      globalThis.location.hostname = 'www.boston.gov';
      globalThis.location.pathname = '/translate_c';

      expect( translation.isOnGoogleTranslate() ).toBe( true );
    } );

    it( 'Returns `false` if the current URL contains a domain or path not belonging to Google Translate', () => {
      globalThis.location = { "hostname": "www.boston.gov" };

      expect( translation.isOnGoogleTranslate() ).toBe( false );

      globalThis.location.hostname = 'd8-ci.boston.gov';

      expect( translation.isOnGoogleTranslate() ).toBe( false );

      globalThis.location.hostname = 'google.com';

      expect( translation.isOnGoogleTranslate() ).toBe( false );

      globalThis.location.hostname = 'googleusercontent.com';

      expect( translation.isOnGoogleTranslate() ).toBe( false );
    } );
  } );

  describe( 'getUrlBeingTranslated()', () => {
    it( 'Returns the value in `localStorage.urlBeingTranslated`', () => {
      const urlBeingTranslated = 'https://www.boston.gov/metrolist/search';
      const DummyComponent = () => ( <>{ translation.getUrlBeingTranslated() }</> );
      const { getByText } = render(
        <LocalStorageMock items={ { urlBeingTranslated } }>
          <DummyComponent />
        </LocalStorageMock>,
      );

      getByText( urlBeingTranslated );
    } );

    it( 'Returns the value in `base.href` if `localStorage.urlBeingTranslated` is empty', () => {
      const urlBeingTranslated = 'https://www.boston.gov/metrolist/search';

      const $base = document.createElement( 'base' );
      $base.href = urlBeingTranslated;
      document.head.appendChild( $base );

      const DummyComponent = () => ( <>{ translation.getUrlBeingTranslated() }</> );
      const { getByText } = render(
        <LocalStorageMock items={ {} }>
          <DummyComponent />
        </LocalStorageMock>,
      );

      getByText( urlBeingTranslated );

      document.head.removeChild( $base );
    } );
  } );

  describe( 'copyGoogleTranslateParametersToNewUrl()', () => {
    const originalConsoleError = console.error;
    const getIframeUrl = ( url ) => ( `https://translate.googleusercontent.com/translate_c?depth=1&pto=aue&rurl=translate.google.com&sl=auto&sp=nmt4&tl=ja&u=${url}&usg=ALkJrhj0_wR4zzdtcpIAb7thW_5nW6hJig` );
    const urlBeingTranslated = 'https://www.boston.gov/metrolist/search';
    const urlToTranslate = 'https://www.boston.gov/metrolist/ami-estimator';
    const metrolistGoogleTranslateIframeUrl = getIframeUrl( urlBeingTranslated );
    const DummyComponent = () => ( <>{ translation.copyGoogleTranslateParametersToNewUrl( urlToTranslate ) }</> );

    afterEach( () => {
      globalThis.location = originalLocation;
      console.error = originalConsoleError;
    } );

    it( 'Copies all parameters', () => {
      delete globalThis.location;
      globalThis.location = { "hostname": "translate.googleusercontent.com" };

      const { getByText } = render(
        <LocalStorageMock items={ { metrolistGoogleTranslateIframeUrl } }>
          <DummyComponent />
        </LocalStorageMock>,
      );

      getByText( getIframeUrl( urlToTranslate ) );
    } );

    it( 'Prints an error to the console if `localStorage.metrolistGoogleTranslateIframeUrl` is missing', () => {
      delete globalThis.location;
      globalThis.location = { "hostname": "translate.googleusercontent.com" };

      const errors = [];
      console.error = ( output ) => errors.push( output );

      render(
        <LocalStorageMock items={ {} }>
          <DummyComponent />
        </LocalStorageMock>,
      );

      expect( errors[0] ).toBe( 'Could not find `metrolistGoogleTranslateIframeUrl` in localStorage' );
    } );

    it( 'Prints an error to the console if the page is not being translated', () => {
      const errors = [];
      console.error = ( output ) => errors.push( output );

      render(
        <LocalStorageMock items={ {} }>
          <DummyComponent />
        </LocalStorageMock>,
      );

      expect( errors[0] ).toBe( 'Google Translate URL not detected (checked for translate.googleusercontent.com, translate.google.com, and /translate_c). Can not copy query parameters to new Google Translate URL.' );
    } );
  } );

  describe( 'resolveLocationConsideringGoogleTranslate()', () => {
    afterEach( () => {
      globalThis.location = originalLocation;
    } );

    it( 'Returns a React Router `location` object with its properties corrected to reflect the translated URL, not the Google Translate URL itself', () => {
      delete globalThis.location;
      globalThis.location = mockLocation;

      const urlBeingTranslated = 'https://www.boston.gov/metrolist/search';
      let resolvedLocation;
      const DummyComponent = () => {
        resolvedLocation = translation.resolveLocationConsideringGoogleTranslate( useLocation() );
        return null;
      };

      render(
        <MemoryRouter initialEntries={[globalThis.location.href]} initialIndex={0}>
          <DummyComponent />
        </MemoryRouter>,
      );

      expect( resolvedLocation ).toMatchObject( {
        "_urlBeingTranslated": urlBeingTranslated,
        "hash": "",
        "pathname": "/metrolist/search",
        "search": globalThis.location.search,
      } );
    } );

    it( 'Stores the Google Translate URL and the URL being translated in localStorage', () => {
      delete globalThis.location;
      globalThis.location = mockLocation;

      const urlBeingTranslated = 'https://www.boston.gov/metrolist/search';
      const DummyComponent = () => {
        translation.resolveLocationConsideringGoogleTranslate( useLocation() );
        return null;
      };

      render(
        <LocalStorageMock items={ {} }>
          <MemoryRouter initialEntries={[globalThis.location.href]} initialIndex={0}>
            <DummyComponent />
          </MemoryRouter>
        </LocalStorageMock>,
      );

      expect( localStorage.getItem( 'metrolistGoogleTranslateIframeUrl' ) ).toBe( globalThis.location.href );
      expect( localStorage.getItem( 'urlBeingTranslated' ) ).toBe( urlBeingTranslated );
    } );

    it( 'Uses `base.href` as a fallback if the page is not being translated, or lacks a query string from which to extract the URL being translated', () => {
      const urlBeingTranslated = 'https://www.boston.gov/metrolist/search';
      let resolvedLocation;

      const $base = document.createElement( 'base' );
      $base.href = urlBeingTranslated;
      document.head.appendChild( $base );

      const DummyComponent = () => {
        resolvedLocation = translation.resolveLocationConsideringGoogleTranslate( useLocation(), false );
        return null;
      };

      render(
        <MemoryRouter initialEntries={ ['/metrolist/search'] } initialIndex={ 0 }>
          <DummyComponent />
        </MemoryRouter>,
      );

      expect( resolvedLocation ).toMatchObject( {
        "_urlBeingTranslated": urlBeingTranslated,
        "pathname": "/metrolist/search",
        "search": "",
      } );

      document.head.removeChild( $base );
    } );
  } );

  describe( 'switchToGoogleTranslateBaseIfNeeded()', () => {
    delete globalThis.location;
    globalThis.location = mockLocation;

    const $base = document.createElement( 'base' );
    $base.href = 'https://www.boston.gov/metrolist/search/';
    document.head.appendChild( $base );

    translation.switchToGoogleTranslateBaseIfNeeded( $base );

    expect( $base.href ).toBe( 'https://translate.googleusercontent.com/' );

    document.head.removeChild( $base );
    globalThis.location = originalLocation;
  } );

  describe( 'switchBackToMetrolistBaseIfNeeded()', () => {
    delete globalThis.location;
    globalThis.location = mockLocation;

    const $base = document.createElement( 'base' );
    $base.href = 'https://translate.googleusercontent.com/';
    document.head.appendChild( $base );

    let resolvedLocation;

    const DummyComponent = () => {
      resolvedLocation = translation.resolveLocationConsideringGoogleTranslate( useLocation() );
      translation.switchBackToMetrolistBaseIfNeeded( resolvedLocation, $base );
      return null;
    };

    render(
      <MemoryRouter initialEntries={[globalThis.location.href]} initialIndex={0}>
        <DummyComponent />
      </MemoryRouter>,
    );

    expect( $base.href ).toBe( 'https://www.boston.gov/metrolist/search' );

    document.head.removeChild( $base );
    globalThis.location = originalLocation;
  } );
} );
