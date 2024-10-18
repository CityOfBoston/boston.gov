import React, { useRef, useState, useEffect } from 'react';
import PropTypes from 'prop-types';

import Stack from '@components/Stack';

import './Reveal.scss';
import { generateRandomNumberString } from '@util/strings';

function Reveal(props) {
  const $content = useRef();
  const id = useRef(`reveal-${generateRandomNumberString()}`);
  const [isExpanded, setExpanded] = useState(false);
  const [isOverflowing, setIsOverflowing] = useState(false);

  // Hardcoded maximum height in pixels
  const maxVisibleHeight = 100; // Adjust this to the desired max height

  // Update the height based on content overflow and expansion state
  useEffect(() => {
    // Ensure each component calculates its own height independently
    if ($content.current.scrollHeight > maxVisibleHeight) {
      setIsOverflowing(true); // Content is too large, so enable the "more" button
      if (!isExpanded) {
        $content.current.style.maxHeight = `${maxVisibleHeight}px`; // Set to collapsed state
      }
    } else {
      setIsOverflowing(false); // Content fits within the visible area, no need for "more" button
      $content.current.style.maxHeight = 'none'; // No height restriction if it fits
    }
  }, [props.children, isExpanded]); // Recheck if children or expanded state changes

  function handleMoreLessClick(event) {
    event.preventDefault();

    setExpanded(!isExpanded); // Toggle expanded state

    if (isExpanded) {
      // Collapse the content
      $content.current.style.maxHeight = `${maxVisibleHeight}px`;
    } else {
      // Expand the content to its full height
      $content.current.style.maxHeight = `${$content.current.scrollHeight}px`;
    }
  }

  return (
    <Stack {...props.stack} space="0.5">
      <Stack
        id={id.current}
        ref={$content}
        {...{ ...props.stack, indent: false }}
        className="ml-reveal__content"
        style={{ overflow: 'hidden', transition: 'max-height 0.3s ease' }} // Ensure smooth transition
      >
        {props.children}
      </Stack>
      {isOverflowing && (  // Show the "more/less" button if content exceeds the threshold
        <button
          className="ml-reveal__more-button"
          href="#"
          onClick={handleMoreLessClick}
          aria-expanded={isExpanded.toString()}
          aria-controls={id.current}
        >
          {isExpanded ? 'less' : 'more…'}
        </button>
      )}
    </Stack>
  );
}

Reveal.propTypes = {
  children: PropTypes.node,
  stack: PropTypes.object,
  className: PropTypes.string,
};

export default Reveal;