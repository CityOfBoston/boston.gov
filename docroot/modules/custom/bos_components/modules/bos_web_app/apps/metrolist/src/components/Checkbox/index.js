import './Checkbox.scss';

import PropTypes from 'prop-types';
import React from 'react';

import { renderChoices } from './methods';

function Checkbox( props ) {
  let modifierClasses = '';

  if ( props.button ) {
    modifierClasses += ' ml-checkbox--button';
  }

  return (
    <div
      className={ `ml-checkbox${modifierClasses}${props.className ? ` ${props.className}` : ''}` }
      data-column-width={ props.columnWidth }
      style={ props.style }
    >
      { renderChoices( props ) }
    </div>
  );
}

Checkbox.defaultProps = {
  "count": 0,
};

Checkbox.propTypes = {
  "button": PropTypes.bool,
  "children": PropTypes.node,
  "className": PropTypes.string,
  "style": PropTypes.object,
  "count": PropTypes.number,
  "criterion": PropTypes.string,
  "subcategoriesOnly": PropTypes.bool,
  "columnWidth": PropTypes.oneOfType( [PropTypes.string, PropTypes.bool] ),
  "required": PropTypes.bool,
  "hasSubcategories": PropTypes.bool,
  "aria-label": PropTypes.string,
  "size": PropTypes.oneOf( ['small', null] ),
  "onChange": PropTypes.func,
};

Checkbox.defaultProps = {
  "required": false,
  "hasSubcategories": false,
  "size": null,
  "onChange": () => {},
};

export default Checkbox;
