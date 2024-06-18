import React, { forwardRef } from 'react';
import PropTypes from 'prop-types';

// import './FormErrorMessage.scss';

const FormErrorMessage = forwardRef( ( props, ref ) => (
    <div
      ref={ ref }
      id={ props.id }
      { ...props }
      className={ `t--subinfo t--err m-t100${props.className ? ` ${props.className}` : ''}` }
      role="alert"
      aria-live="assertive"
      // hidden
      data-testid="ml-form-error-message"
    >{ props.children }</div>
) );

FormErrorMessage.displayName = 'FormErrorMessage';

FormErrorMessage.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "id": PropTypes.string.isRequired,
};

export default FormErrorMessage;
