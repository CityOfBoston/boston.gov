import React, { forwardRef } from 'react';
import PropTypes from 'prop-types';

import './Row.scss';

const Row = forwardRef( ( props, ref ) => {
  let RowElement;
  const targetProps = { ...props };
  let className = `ml-row`;

  if ( props.align ) {
    delete targetProps.align;
    className += ` ml-row--align-${props.align}`;
  }

  if ( props.stackUntil ) {
    delete targetProps.stackUntil;
    className += ` ml-row--stack-until ml-row--stack-until-${props.stackUntil}`;
  }

  if ( props.stackAt ) {
    delete targetProps.stackAt;
    className += ` ml-row--stack-at-${props.stackAt}`;
  }

  if ( props.space ) {
    delete targetProps.space;
    className += ` ml-row--space-${props.space}`;
  }

  if ( props.className ) {
    className += ` ${props.className}`;
  }

  if ( props.as ) {
    delete targetProps.as;
    RowElement = React.createElement( props.as, { ...targetProps, className, ref }, props.children );
  } else {
    RowElement = React.createElement( 'div', { ...targetProps, className, ref }, props.children );
  }

  return RowElement;
} );

Row.displayName = 'Row';

Row.propTypes = {
  "as": PropTypes.oneOfType( [PropTypes.string, PropTypes.node] ),
  "align": PropTypes.oneOf( ['beginning', 'middle', 'end'] ),
  "stackUntil": PropTypes.oneOf( ['xsmall', 'small', 'medium', 'large', 'xlarge'] ),
  "stackAt": PropTypes.oneOf( ['xsmall', 'small', 'medium', 'large', 'xlarge'] ),
  "space": PropTypes.string,
  "children": PropTypes.node,
  "className": PropTypes.string,
};

export default Row;
