import React from 'react';
import PropTypes from 'prop-types';

import { formatKey, formatValue } from './methods';

import './HomeInfo.scss';

function HomeInfo( { className, info } ) {
  return (
    <dl className={ `ml-home-info${className ? ` ${className}` : ''}` }>{
      Object.keys( info )
        .map( ( key, index ) => {
          const value = info[key];
          const formattedKey = formatKey( { key, value, info } );
          const formattedValue = formatValue( { key, value, info } );
          const isRelevantValue = ( ( formattedValue !== null ) && ( formattedValue !== 'false' ) && ( formattedValue !== '' ) );
          const isRelevantKey = ( key !== 'assignment' );
          const isIrrelevantDueDate = (
            ( key === 'applicationDueDate' )
            && ( value === '' )
          );

          if ( isRelevantKey && !isIrrelevantDueDate ) {
            return (
              <div key={ index }>
                <dt className="ml-home-info__key">{ formattedKey }</dt>
                { isRelevantValue && <dd className="ml-home-info__value">{ formattedValue }</dd> }
              </div>
            );
          }

          return undefined;
        } )
    }</dl>
  );
}

HomeInfo.propTypes = {
  "className": PropTypes.string,
  "info": PropTypes.object,
};

export default HomeInfo;
