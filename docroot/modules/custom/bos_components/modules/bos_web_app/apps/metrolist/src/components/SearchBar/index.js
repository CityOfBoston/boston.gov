import React, { useState, useEffect } from 'react';
import PropTypes from 'prop-types';
import Stack from '@components/Stack';
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

function SearchBar( props ) {
  const [keyphrase, setKeyphrase] = useState( props.keyphrase || '' );

  useEffect( () => {
    setKeyphrase( props.keyphrase );
  }, [props] );

  const handleInput = ( ( event ) => {
    const $input = event.target;
    if (keyphrase <= props.maxLength) {
      setKeyphrase($input.value)
    } 
  } );

  if ( isIEorEdge ) {
    import( './SearchBar.ie-edge.css' ).then();
  } else {
    import( './SearchBar.scss' ).then();
  }

  return (
    <div
      className="search-bar"
    >
      <Stack>
        <p className="search-bar-container">
          <input
            className={ `property-name-search-bar-input` }
            type="text"
            id="property-name"
            name="keyphrase"
            value={ keyphrase }
            onChange={ handleInput }
            placeholder= "Property Name"
            data-testid={ `${props.criterion}Keyphrase` }
          />
        </p>
      </Stack>
    </div>
  );
}

SearchBar.displayName = 'SearchBar';

SearchBar.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "criterion": PropTypes.string,
  "keyphrase": PropTypes.string,
  "maxLength": PropTypes.number
};

export default SearchBar;
