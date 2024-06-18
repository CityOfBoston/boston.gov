import React from 'react';
import PropTypes from 'prop-types';
import { numericString } from 'airbnb-prop-types';

import './Icon.scss';

function Icon( props ) {
  const attributes = {
    "className": `ml-icon ml-icon--${props.icon}`,
    ...props,
  };
  delete attributes.fallbackExtension;

  return (
    <picture>
      <source data-testid="ml-icon__svg" type="image/svg+xml" srcSet={ `/images/${props.icon}.sv\g` } />
      <img
        { ...attributes }
        src={ `/images/${props.icon}.${props.fallbackExtension}` }
        srcSet={ `/images/${props.icon}.${props.fallbackExtension} 1x, /images/${props.icon}@2x.${props.fallbackExtension} 2x, /images/${props.icon}@3x.${props.fallbackExtension} 3x` }
        alt={ props.alt }
      />
    </picture>
  );
}

Icon.propTypes = {
  "icon": PropTypes.string.isRequired,
  "width": numericString(),
  "height": numericString(),
  "alt": PropTypes.string.isRequired,
  "fallbackExtension": PropTypes.string,
};

Icon.defaultProps = {
  "fallbackExtension": "png",
};

export default Icon;
