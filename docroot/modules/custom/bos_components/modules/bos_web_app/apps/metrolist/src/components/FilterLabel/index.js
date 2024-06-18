import React from 'react';
import PropTypes from 'prop-types';

const FilterLabel = ( props ) => <>{ props.children }</>;
FilterLabel.displayName = "FilterLabel";
FilterLabel.propTypes = { "children": PropTypes.node };

export default FilterLabel;
