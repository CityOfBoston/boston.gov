import React from 'react';
import PropTypes from 'prop-types';

import './Column.scss';

function Column( props ) {
  return (
    <div
      className={ `ml-column${props.className ? props.className : ''}` }
      data-column-width={ props.width }
    >{ props.children }</div>
  );
}

Column.propTypes = {
  "className": PropTypes.string,
  "children": PropTypes.node,
  "width": PropTypes.string,
};

export default Column;
