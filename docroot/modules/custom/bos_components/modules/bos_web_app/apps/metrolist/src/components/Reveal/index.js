import React, { useRef, useState } from 'react';
import PropTypes from 'prop-types';

import Stack from '@components/Stack';

import './Reveal.scss';
import { generateRandomNumberString } from '@util/strings';

function Reveal( props ) {
  const $content = useRef();
  const id = useRef( `reveal-${generateRandomNumberString()}` );
  const [isExpanded, setExpanded] = useState( false );

  function handleMoreLessClick( event ) {
    setExpanded( !isExpanded );

    if ( isExpanded ) {
      $content.current.style.maxHeight = '';
    } else {
      $content.current.style.maxHeight = `${48 * ( props.children.length + 1 )}px`;
    }

    event.preventDefault();
  }

  return (
    <Stack { ...props.stack } space="0.5">
      <Stack id={ id.current } ref={ $content } { ...{ ...props.stack, "indent": false } } className="ml-reveal__content">
        { props.children }
      </Stack>
      {
        ( props.children.length > 3 )
        && (
          <button
            className="ml-reveal__more-button"
            href="#"
            onClick={ handleMoreLessClick }
            aria-expanded={ isExpanded.toString() }
            aria-controls={ id.current }
          >
            { isExpanded ? 'less' : 'more…' }
          </button>
        )
      }
    </Stack>
  );
}

Reveal.propTypes = {
  "children": PropTypes.node,
  "stack": PropTypes.object,
  "className": PropTypes.string,
};

export default Reveal;
