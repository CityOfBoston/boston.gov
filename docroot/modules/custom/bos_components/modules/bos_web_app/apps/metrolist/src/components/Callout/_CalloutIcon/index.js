import React from 'react';
import PropTypes from 'prop-types';

import './CalloutIcon.scss';

function CalloutIcon( props ) {
  return (
    <div data-testid="ml-callout__icon" className={ `ml-callout__icon${props.className ? ` ${props.className}` : ''}` }>
      { props.children }
    </div>
  );
}

CalloutIcon.displayName = 'CalloutIcon';

CalloutIcon.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
};

export default CalloutIcon;
