import React, { useEffect, useState } from 'react';
import PropTypes from 'prop-types';

import Home from '@components/Home';
import Stack from '@components/Stack';
import Inset from '@components/Inset';
import Icon from '@components/Icon';

import { homeObject } from '@util/validation';

import './ResultsPanel.scss';

function ResultsPanel( props ) {
  const {
    homes, className, columnWidth, filters,
  } = props;
  const attributes = { ...props };
  const [showHomes, setShowHomes] = useState( false );

  if ( homes && ( homes.length > 0 ) ) {
    delete attributes.homes;
  }

  if ( columnWidth ) {
    delete attributes.columnWidth;
    attributes['data-column-width'] = columnWidth;
  }

  if ( filters ) {
    delete attributes.filters;
  }

  delete attributes.homesHaveLoaded;

  const Homes = () => (
    ( homes && homes.length )
      ? homes.map( ( home ) => <Home key={ home.id } home={ home } filters={ filters } /> )
      : (
        <div className="ml-results-panel__home-status">
          <Icon icon="house-missing" width="134" height="83" alt="" />
          <p className="ml-results-panel__home-status-text">No homes match the selected filters.</p>
        </div>
      )
  );

  const NoHomesAvailable = () => (
    <div className="ml-results-panel__home-status">
      <Icon icon="house-loading" fallbackExtension="gif" width="134" height="83" alt="" />
      <p className="ml-results-panel__home-status-text">Loading homes…</p>
    </div>
  );

  useEffect( () => {
    const showHomesAfterLoadingAnimationCompletes = setTimeout( () => setShowHomes( true ), 1500 );

    return () => clearTimeout( showHomesAfterLoadingAnimationCompletes );
  }, [homes] );

  return (
    <article
      data-testid="ml-results-panel"
      className={ `ml-results-panel${className ? ` ${className}` : ''}` }
      { ...attributes }
    >
      <div className="sr-only b b--fw">
        <div className="b-c b-c--nbp b-c--ntp">
          <div className="str">
            <div className="str-c">
              <div className="str-t"><h2>Results</h2></div>
            </div>
          </div>
        </div>
      </div>
      <Inset until="large">
        <Stack space="panel">
        {
          showHomes
            ? <Homes />
            : <NoHomesAvailable />
        }
        </Stack>
      </Inset>
    </article>
  );
}

ResultsPanel.propTypes = {
  "homes": PropTypes.arrayOf( homeObject ),
  "homesHaveLoaded": PropTypes.bool,
  "columnWidth": PropTypes.string,
  "className": PropTypes.string,
  "filters": PropTypes.object,
};

ResultsPanel.defaultProps = {
  "homes": [],
};

export default ResultsPanel;
