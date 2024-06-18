import React from 'react';
import PropTypes from 'prop-types';

import './CalloutCta.scss';

function CalloutCta( props ) {
  return (
    <div data-testid="ml-callout__cta" className={ `ml-callout__cta${props.className ? ` ${props.className}` : ''}` }>
      { props.children }
    </div>
  );
}

CalloutCta.displayName = 'CalloutCTA';

CalloutCta.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
};

export default CalloutCta;
