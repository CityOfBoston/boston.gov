import React from 'react';
import PropTypes from 'prop-types';

import './Required.scss';

const supportsRequiredAttribute = ( 'required' in document.createElement( 'input' ) );

function Required( props ) {
  return (
    <span
      className={ `ml-required${props.className ? ` ${props.className}` : ''}` }
      title="required"
      aria-hidden={ supportsRequiredAttribute ? 'true' : 'false' }
    >*</span>
  );
}

Required.displayName = 'Required';

Required.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
};

export default Required;
