import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';

import Stack from '@components/Stack';

import { formatIncome } from '@util/strings';
import { getGlobalThis } from '@util/objects';

const globalThis = getGlobalThis();

const isIE = /* @cc_on!@ */false || !!document.documentMode;
const isEdge = ( globalThis.navigator.userAgent.indexOf( "Edge" ) > -1 ); // Excludes Chromium-based Edge which reports “Edg” without the e
const isIEorEdge = ( isIE || isEdge );

// NOTE: Normally we would do something like this to ensure multiple Ranges
// do not have conflicting HTML IDs, but for some reason it completely breaks
// the synchronization between the fill and the thumbs to have the randomDomId number on the end.
// It also breaks when completely removing the for/id relationship between the label and input.
// ------
// const randomDomId = generateRandomNumberString();
// const lowerBoundId = `lower-bound-${randomDomId}`;
// const upperBoundId = `upper-bound-${randomDomId}`;

function Range( props ) {
  const { min, max } = props;
  const [lowerBound, setLowerBound] = useState( props.lowerBound || min );
  const [upperBound, setUpperBound] = useState( props.upperBound || max );
  const [outOfBounds, setOutOfBounds] = useState( false );

  useEffect( () => {
    setLowerBound( props.lowerBound );
    setUpperBound( props.upperBound );
  }, [props] );

  useEffect( () => {
    setOutOfBounds( lowerBound > upperBound );
  }, [lowerBound, upperBound] );

  const formatValue = ( value ) => {
    let formattedValue;

    switch ( props.valueFormat ) {
      case '%':
        formattedValue = `${value}%`;
        break;

      case '$':
        formattedValue = formatIncome( value * 100, false );
        break;

      default:
        formattedValue = value;
    }

    return formattedValue;
  };

  const handleInput = ( ( event ) => {
    const $input = event.target;

    switch ( $input.id ) { // eslint-disable-line default-case
      case 'lower-bound':
        setLowerBound( parseInt( $input.value, 10 ) );
        $input.parentNode.style.setProperty( `--${$input.id}`, +$input.value );
        break;

      case 'upper-bound':
        setUpperBound( parseInt( $input.value, 10 ) );
        $input.parentNode.style.setProperty( `--${$input.id}`, +$input.value );
        break;
    }

    setOutOfBounds( lowerBound > upperBound );
  } );

  if ( isIEorEdge ) {
    import( './Range.ie-edge.css' ).then();
  } else {
    import( './Range.scss' ).then();
  }

  const RangeMultiInput = ( isIEorEdge ? Stack : 'div' );

  return (
    <div
      className="ml-range"
      style={ {
        "--lower-bound": lowerBound,
        "--upper-bound": upperBound,
        "--min": min,
        "--max": max,
      } }
    >
      <Stack space="1">
        <p>
          <span className={ `ml-range__review${outOfBounds ? ` ml-range__review--inverted` : ''}` }>
            <output className="ml-range__output" htmlFor="lower-bound">{ formatValue( lowerBound ) }</output>
            <span className="en-dash">–</span>
            <output className="ml-range__output" htmlFor="upper-bound">{ formatValue( upperBound ) }</output>
          </span>
          { props.maxValueAppend && ( upperBound === max ) && props.maxValueAppend() }
          { props.valueAppend && props.valueAppend() }
        </p>
        <RangeMultiInput
          space={ isIEorEdge ? '1.5' : undefined }
          className="ml-range__multi-input"
          role="group"
        >
          <label
            className={ isIEorEdge ? undefined : 'sr-only' }
            htmlFor="lower-bound"
          >{ outOfBounds ? 'Maximum' : 'Minimum' }</label>
          <input
            className={ `ml-range__input${outOfBounds ? ` ml-range__input--inverted` : ''}` }
            type="range"
            id="lower-bound"
            name="lowerBound"
            min={ min }
            value={ lowerBound || min }
            max={ max }
            step={ props.step }
            onChange={ handleInput }
            data-testid={ `${props.criterion}LowerBound` }
          />

          <label
            className={ isIEorEdge ? undefined : 'sr-only' }
            htmlFor="upper-bound"
          >{ outOfBounds ? 'Minimum' : 'Maximum' }</label>
          <input
            className={ `ml-range__input${outOfBounds ? ` ml-range__input--inverted` : ''}` }
            type="range"
            id="upper-bound"
            name="upperBound"
            min={ min }
            value={ upperBound || max }
            max={ max }
            step={ props.step }
            onChange={ handleInput }
            data-testid={ `${props.criterion}UpperBound` }
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
  "valueFormat": PropTypes.oneOf( ['%', '$'] ),
  "valueAppend": PropTypes.func,
  "maxValueAppend": PropTypes.func,
};

export default Range;