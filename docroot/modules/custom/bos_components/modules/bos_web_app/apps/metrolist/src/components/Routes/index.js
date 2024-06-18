import React from 'react';
import PropTypes from 'prop-types';
import {
  Switch, Route,
  useLocation,
} from 'react-router-dom';

import { resolveLocationConsideringGoogleTranslate } from '@util/translation';

import Search from '@components/Search';
import AmiEstimator from '@components/AmiEstimator';

import './Routes.scss';

function Routes() {
  return (
    <Switch location={ resolveLocationConsideringGoogleTranslate( useLocation() ) }>
      <Route path="/metrolist/search">
        <Search />
      </Route>
      <Route path="/metrolist/ami-estimator">
        <AmiEstimator />
      </Route>
    </Switch>
  );
}

Routes.displayName = 'Routes';

Routes.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
};

export default Routes;
