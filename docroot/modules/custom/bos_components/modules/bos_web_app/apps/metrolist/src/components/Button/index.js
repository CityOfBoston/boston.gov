import React, { forwardRef } from 'react';
import PropTypes from 'prop-types';

import './Button.scss';

const Button = forwardRef( ( props, ref ) => {
  let htmlElement;
  const attributes = { ...props };

  switch ( props.as ) {
    case 'a':
    case 'link':
      htmlElement = 'a';
      break;
    case 'button':
    default:
      htmlElement = 'button';
  }

  delete attributes.as;
  delete attributes.children;
  delete attributes.variant;

  return (
    React.createElement(
      htmlElement,
      {
        ...attributes,
        ref,
        "className": `btn btn--metrolist-${props.variant}${props.className ? ` ${props.className}` : ''}`,
      },
      props.children,
    )
  );
} );

Button.displayName = 'Button';

Button.propTypes = {
  "as": PropTypes.string,
  "variant": PropTypes.oneOf( ['primary', 'secondary'] ),
  "accesskey": PropTypes.string,
  "href": PropTypes.string,
  "children": PropTypes.node,
  "className": PropTypes.string,
};

Button.defaultProps = {
  "variant": "secondary",
};

export default Button;
