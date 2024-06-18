import React from 'react';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom/extend-expect';

import Icon from './index';

describe( 'Icon', () => {
  it( 'Renders the specified icon as an SVG with PNG fallbacks', () => {
    const { getByAltText, getByTestId } = render( <Icon icon="deposit_check" alt="" /> );
    const $svgIcon = getByTestId( 'ml-icon__svg' ); // picture > source
    const $pngIcons = getByAltText( '' ); // img

    // TODO: In a Production build this would be 'https://assets.boston.gov/icons/metrolist/deposit_check.png';
    // May need a way to expect different values depending on environment.
    // But inside of Jest, NODE_ENV will be "test"—neither "development" nor "production"
    expect( $svgIcon ).toHaveAttribute( 'type', 'image/svg+xml' );
    expect( $svgIcon ).toHaveAttribute( 'srcset', '/images/deposit_check.svg' );
    expect( $pngIcons ).toHaveAttribute( 'src', '/images/deposit_check.png' );
    expect( $pngIcons ).toHaveAttribute( 'srcset', '/images/deposit_check.png 1x, /images/deposit_check@2x.png 2x, /images/deposit_check@3x.png 3x' );
  } );
} );
