import React from 'react';
import PropTypes from 'prop-types';

import Stack from '@components/Stack';
import { generateRandomNumberString } from '@util/strings';

import './ProgressBar.scss';

function ProgressBar( props ) {
  const domId = `progress-label-${generateRandomNumberString()}`;

  return (
    <Stack className={ `ml-progress-bar${props.className ? ` ${props.className}` : ''}` } space="1">
      <progress className="ml-progress-bar__progress" min="1" value={ props.current } max={ props.total } aria-labelledby={ domId }>{ props.children }</progress>
      <p id={ domId } className="ml-progress-bar__step">Step { props.current } of { props.total }</p>
    </Stack>
  );
}

ProgressBar.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "current": PropTypes.number,
  "total": PropTypes.number,
};

export default ProgressBar;
