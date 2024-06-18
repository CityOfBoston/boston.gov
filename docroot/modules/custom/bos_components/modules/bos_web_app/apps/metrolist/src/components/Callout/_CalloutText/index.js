import React from 'react';
import PropTypes from 'prop-types';

import './CalloutText.scss';

function CalloutText( props ) {
  return (
    <div data-testid="ml-callout__text" className={ `ml-callout__text${props.className ? ` ${props.className}` : ''}` }>
      { props.children }
    </div>
  );
}

CalloutText.displayName = 'CalloutText';

CalloutText.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
};

export default CalloutText;
