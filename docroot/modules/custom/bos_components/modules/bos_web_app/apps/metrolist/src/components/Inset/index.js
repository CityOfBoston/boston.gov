import React from 'react';
import PropTypes from 'prop-types';

import './Inset.scss';

function Inset( props ) {
  return (
    <div className={
      `ml-inset${
        props.className ? ` ${props.className}` : ''
      }${
        props.until ? ` ml-inset--until-${props.until}` : ''
      }`
    }>
      { props.children }
    </div>
  );
}

Inset.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "until": PropTypes.oneOf( ['xsmall', 'small', 'medium', 'large', 'xlarge'] ),
};

export default Inset;
