import React from 'react';
import PropTypes from 'prop-types';

import './CalloutHeading.scss';

function CalloutHeading( props ) {
  let elementName;
  const attributes = { ...props };

  if ( props.as ) {
    delete attributes.as;
    elementName = props.as;
  } else {
    elementName = 'h3';
  }

  return (
    React.createElement(
      elementName,
      {
        ...attributes,
        "className": ( `ml-callout__heading${props.className ? ` ${props.className}` : ''}` ),
      },
      props.children,
    )
  );
}

CalloutHeading.displayName = 'CalloutHeading';

CalloutHeading.propTypes = {
  "className": PropTypes.string,
  "as": PropTypes.string,
  "children": PropTypes.node,
};

export default CalloutHeading;
