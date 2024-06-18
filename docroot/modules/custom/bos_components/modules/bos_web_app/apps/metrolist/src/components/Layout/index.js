import React from 'react';
import PropTypes from 'prop-types';

function Layout( props ) {
  return (
    <div className={ props.className }>{ props.children }</div>
  );
}

Layout.propTypes = {
  "className": PropTypes.string,
  "children": PropTypes.node,
};

export default Layout;
