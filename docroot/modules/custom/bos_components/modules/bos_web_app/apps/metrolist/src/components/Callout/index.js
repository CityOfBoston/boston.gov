import React from 'react';
import PropTypes from 'prop-types';

import './Callout.scss';

import CalloutIcon from './_CalloutIcon';
import CalloutHeading from './_CalloutHeading';
import CalloutText from './_CalloutText';
import CalloutCta from './_CalloutCta';

function Callout( props ) {
  let elementName;
  const attributes = { ...props };

  if ( props.as ) {
    delete attributes.as;
    elementName = props.as;
  } else {
    elementName = 'div';
  }

  return (
    React.createElement(
      elementName,
      {
        ...attributes,
        "className": ( `ml-callout${props.className ? ` ${props.className}` : ''}` ),
      },
      props.children,
    )
  );
}

Callout.propTypes = {
  "children": PropTypes.node,
  "as": PropTypes.string,
  "className": PropTypes.string,
};

Callout.Icon = CalloutIcon;
Callout.Heading = CalloutHeading;
Callout.Text = CalloutText;
Callout.CTA = CalloutCta;

export default Callout;
