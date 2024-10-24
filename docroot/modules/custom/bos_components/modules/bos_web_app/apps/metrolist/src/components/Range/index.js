import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';

import Stack from '@components/Stack';
import { formatIncome } from '@util/strings';
import { getGlobalThis } from '@util/objects';

const globalThis = getGlobalThis();

const isIE = /* @cc_on!@ */false || !!document.documentMode;
const isEdge = (globalThis.navigator.userAgent.indexOf("Edge") > -1); // Excludes Chromium-based Edge which reports “Edg” without the e
const isIEorEdge = (isIE || isEdge);

function Range(props) {
  const { min, max } = props;
  const [lowerBound, setLowerBound] = useState(props.lowerBound || min);
  const [upperBound, setUpperBound] = useState(props.upperBound || max);
  const [tempLowerBound, setTempLowerBound] = useState(lowerBound);
  const [tempUpperBound, setTempUpperBound] = useState(upperBound);
  const [outOfBounds, setOutOfBounds] = useState(false);

  // Update actual bounds when props change
  useEffect(() => {
    setLowerBound(props.lowerBound);
    setUpperBound(props.upperBound);
    setTempLowerBound(props.lowerBound); // Sync temp values as well
    setTempUpperBound(props.upperBound);
  }, [props.lowerBound, props.upperBound]);

  // Check if temp bounds are out of bounds
  useEffect(() => {
    setOutOfBounds(tempLowerBound > tempUpperBound);
  }, [tempLowerBound, tempUpperBound]);

  const formatValue = (value) => {
    let formattedValue;
    switch (props.valueFormat) {
      case '%':
        formattedValue = `${value}%`;
        break;
      case '$':
        formattedValue = formatIncome(value * 100, false);
        break;
      default:
        formattedValue = value;
    }
    return formattedValue;
  };

  // Handle temp value changes on input change (while dragging)
  const handleInput = (event) => {
    const $input = event.target;
    const value = parseInt($input.value, 10);

    if ($input.id === 'lower-bound') {
      setTempLowerBound(value);
      $input.parentNode.style.setProperty(`--${$input.id}`, +$input.value);
    } else if ($input.id === 'upper-bound') {
      setTempUpperBound(value);
      $input.parentNode.style.setProperty(`--${$input.id}`, +$input.value);
    }
    setOutOfBounds(tempLowerBound > tempUpperBound);
  };

  // Commit temp values to actual state on drag end
  const handleInputCommit = () => {
    setLowerBound(tempLowerBound);
    setUpperBound(tempUpperBound);
  };

  if (isIEorEdge) {
    import('./Range.ie-edge.css').then();
  } else {
    import('./Range.scss').then();
  }

  const RangeMultiInput = isIEorEdge ? Stack : 'div';

  return (
    <div
      className="ml-range"
      style={{
        "--lower-bound": tempLowerBound,
        "--upper-bound": tempUpperBound,
        "--min": min,
        "--max": max,
      }}
    >
      <Stack space="1">
        <p>
          <span className={`ml-range__review${outOfBounds ? ` ml-range__review--inverted` : ''}`}>
            <output className="ml-range__output" htmlFor="lower-bound">{formatValue(tempLowerBound)}</output>
            <span className="en-dash">–</span>
            <output className="ml-range__output" htmlFor="upper-bound">{formatValue(tempUpperBound)}</output>
          </span>
          {props.maxValueAppend && (tempUpperBound === max) && props.maxValueAppend()}
          {props.valueAppend && props.valueAppend()}
        </p>
        <RangeMultiInput
          space={isIEorEdge ? '1.5' : undefined}
          className="ml-range__multi-input"
          role="group"
        >
          <label
            className={isIEorEdge ? undefined : 'sr-only'}
            htmlFor="lower-bound"
          >{outOfBounds ? 'Maximum' : 'Minimum'}</label>
          <input
            className={`ml-range__input${outOfBounds ? ` ml-range__input--inverted` : ''}`}
            type="range"
            id="lower-bound"
            name="lowerBound"
            min={min}
            value={tempLowerBound || min}
            max={max}
            step={props.step}
            onChange={handleInput}
            onMouseUp={handleInputCommit}  // Commit on mouse up
            onTouchEnd={handleInputCommit} // Commit on touch end
            data-testid={`${props.criterion}LowerBound`}
          />

          <label
            className={isIEorEdge ? undefined : 'sr-only'}
            htmlFor="upper-bound"
          >{outOfBounds ? 'Minimum' : 'Maximum'}</label>
          <input
            className={`ml-range__input${outOfBounds ? ` ml-range__input--inverted` : ''}`}
            type="range"
            id="upper-bound"
            name="upperBound"
            min={min}
            value={tempUpperBound || max}
            max={max}
            step={props.step}
            onChange={handleInput}
            onMouseUp={handleInputCommit}  // Commit on mouse up
            onTouchEnd={handleInputCommit} // Commit on touch end
            data-testid={`${props.criterion}UpperBound`}
          />
        </RangeMultiInput>
      </Stack>
    </div>
  );
}

Range.displayName = 'Range';

Range.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "criterion": PropTypes.string,
  "min": PropTypes.number,
  "step": PropTypes.number,
  "max": PropTypes.number,
  "lowerBound": PropTypes.number,
  "upperBound": PropTypes.number,
  "valueFormat": PropTypes.oneOf(['%', '$']),
  "valueAppend": PropTypes.func,
  "maxValueAppend": PropTypes.func,
};

export default Range;
