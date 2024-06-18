import React, { forwardRef } from 'react';
import PropTypes from 'prop-types';

import './Stack.scss';

const Stack = forwardRef( ( props, ref ) => {
  let StackElement;
  const {
    as, className, space, indent, reverseAt, children, align, alignAt,
  } = props;
  const attributes = { ...props };
  let stackClasses = '';
  delete attributes.indent;

  if ( space ) {
    delete attributes.space;
    stackClasses += ` ml-stack--space-${space.replace( '.', '_' )}`;
  }

  if ( indent ) {
    delete attributes.indent;
    stackClasses += ` ml-stack--indent-${indent}`;
  }

  if ( align && alignAt ) {
    delete attributes.align;
    delete attributes.alignAt;
    align.forEach( ( alignment, index ) => {
      stackClasses += ` ml-stack--align-${alignment}-${alignAt[index]}`;
    } );
  }

  if ( reverseAt ) {
    delete attributes.reverseAt;
    stackClasses += ` ml-stack--reverse-${reverseAt}`;
  }

  if ( as ) {
    delete attributes.as;
    StackElement = React.createElement(
      as,
      {
        ...attributes,
        ref,
        "className": `ml-stack${stackClasses}${className ? ` ${className}` : ''}`,
      },
      children,
    );
  } else {
    StackElement = React.createElement(
      'div',
      {
        ...attributes,
        ref,
        "className": `ml-stack${stackClasses}${className ? ` ${className}` : ''}`,
      },
      children,
    );
  }

  return StackElement;
} );

Stack.displayName = 'Stack';

Stack.propTypes = {
  "as": PropTypes.oneOfType( [PropTypes.string, PropTypes.node] ),
  "children": PropTypes.node,
  "className": PropTypes.string,
  "space": PropTypes.string,
  "indent": PropTypes.oneOfType( [PropTypes.string, PropTypes.bool] ),
  "alignAt": PropTypes.arrayOf( PropTypes.oneOf( ['xsmall', 'small', 'medium', 'large', 'xlarge'] ) ),
  "align": PropTypes.arrayOf( PropTypes.oneOf( ['beginning', 'middle', 'end'] ) ),
  "reverseAt": PropTypes.oneOf( ['xsmall', 'small', 'medium', 'large', 'xlarge'] ),
};

export default Stack;
