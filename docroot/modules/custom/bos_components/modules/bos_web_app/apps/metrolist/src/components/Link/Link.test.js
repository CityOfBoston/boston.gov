import '__mocks__/matchMedia';
import React from 'react';
import { render, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';
import { MemoryRouter } from 'react-router-dom';

import { act } from 'react-dom/test-utils';
import Link from './index';

describe( 'Link', () => {
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

  beforeAll( () => {
    jest.useFakeTimers();
  } );

  afterAll( () => {
    jest.useRealTimers();
  } );

  it( 'Renders', () => {
    render(
      <MemoryRouter>
        <Link to="/" />
      </MemoryRouter>,
    );
  } );

  it( 'Switches the base.href to the Google Translate iframe domain if necessary', () => {
    delete globalThis.location;
    globalThis.location = mockLocation;

    const realLocationOrigin = 'https://www.boston.gov/metrolist/search/';
    const $base = document.createElement( 'base' );
    $base.href = realLocationOrigin;
    document.head.appendChild( $base );

    const { getByTestId } = render(
      <MemoryRouter initialEntries={ ['/metrolist/search'] } initialIndex={ 0 }>
        <Link to="/metrolist/search?page=2">Page 2</Link>
      </MemoryRouter>,
    );
    const page2Link = getByTestId( 'ml-link' );

    act( () => {
      fireEvent.click( page2Link );
    } );

    expect( $base.href ).toBe( `${mockLocation.origin}/` );

    jest.advanceTimersByTime( 1000 );

    expect( $base.href ).toBe( realLocationOrigin );
  } );
} );
