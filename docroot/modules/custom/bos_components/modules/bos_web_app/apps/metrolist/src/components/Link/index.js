import React from 'react';
import PropTypes from 'prop-types';
import { Link as ReactRouterLink, useLocation } from 'react-router-dom';
import {
  switchToGoogleTranslateBaseIfNeeded,
  switchBackToMetrolistBaseIfNeeded,
  resolveLocationConsideringGoogleTranslate,
} from '@util/translation';

import './Link.scss';

function handleClick( location ) {
  const $base = document.querySelector( 'base[href]' );
  const resolvedLocation = resolveLocationConsideringGoogleTranslate( location );

  switchToGoogleTranslateBaseIfNeeded( $base );

  setTimeout( () => switchBackToMetrolistBaseIfNeeded( resolvedLocation, $base ), 125 );
}

function Link( props ) {
  const location = useLocation();

  return (
    <ReactRouterLink
      data-testid="ml-link"
      { ...props }
      onClick={ ( event ) => {
        handleClick( location );
        if ( props.onClick ) {
          props.onClick( event );
        }
      } }
    >
      { props.children }
    </ReactRouterLink>
  );
}

Link.displayName = 'Link';

Link.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "onClick": PropTypes.func,
};

export default Link;
