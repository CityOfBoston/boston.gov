import React, { forwardRef } from 'react';
import PropTypes from 'prop-types';

import { propTypeErrorMessage } from '@util/errors';

import './RadioGroup.scss';

const RadioGroup = forwardRef( ( props, ref ) => (
  <div className={ `ml-radio-group${props.className ? ` ${props.className}` : ''}` } onChange={ props.onChange }>{
    props.values.split( ',' )
      .map( ( value, index ) => (
        <label key={ index } className="ml-radio-group__label">
          <input
            ref={ ( index === 0 ) ? ref : null }
            className="ml-radio-group__form-control"
            name={ props.criterion }
            value={ value }
            type="radio"
            aria-describedby={ props['aria-describedby'] }
            aria-label={ props['aria-label'] }
            required={ props.required }
            defaultChecked={ value === props.value }
          />
          <span className="ml-radio-group__text">{ value }</span>
        </label>
      ) )
  }</div>
) );

RadioGroup.displayName = 'RadioGroup';

RadioGroup.propTypes = {
  "children": PropTypes.node,
  "required": PropTypes.bool,
  "className": PropTypes.string,
  "criterion": PropTypes.string,
  "values": function commaDelimited( props, propName, componentName ) {
    const prop = props[propName];

    if ( prop.indexOf( ',' ) === -1 ) {
      return new Error( propTypeErrorMessage( {
        propName,
        componentName,
        "got": prop,
        "expected": "comma-delimited string",
        "example": "0,1,2,3+",
      } ) );
    }

    return null;
  },
  "value": PropTypes.string,
  "onChange": PropTypes.func,
  "aria-describedby": PropTypes.string,
  "aria-label": PropTypes.string,
};

export default RadioGroup;
