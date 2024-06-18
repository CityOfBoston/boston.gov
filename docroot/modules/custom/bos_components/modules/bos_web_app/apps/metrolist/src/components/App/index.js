import React from 'react';
import { useLocation } from 'react-router-dom';

import { slugify } from '@util/strings';
import { resolveLocationConsideringGoogleTranslate } from '@util/translation';

import Layout from '@components/Layout';
import AppHeader from '@components/AppHeader';
import Routes from '@components/Routes';

// import '@patterns/stylesheets/public.css';
import './App.scss';

function App() {
  const location = resolveLocationConsideringGoogleTranslate( useLocation() );
  const baselessPathname = location.pathname.replace( /^\/metrolist\//, '/' );
  let rootPathSlug;

  if ( baselessPathname.lastIndexOf( '/' ) === 0 ) {
    rootPathSlug = slugify( baselessPathname );
  } else {
    rootPathSlug = slugify( baselessPathname.substring( 0, baselessPathname.lastIndexOf( '/' ) ) );
  }

  // Make sure that localStorage.amiRecommendation is a valid number value.
  let amiRecommendation = parseInt( localStorage.getItem( 'amiRecommendation' ), 10 );
  if ( Number.isNaN( amiRecommendation ) || ( Math.sign( amiRecommendation ) < 1 ) ) {
    localStorage.setItem( 'amiRecommendation', '0' );
    amiRecommendation = 0;
  }

  return (
    <Layout className={ `b b--g b--fx ml-app ml-app--${rootPathSlug}` }>
      <AppHeader />
      <Routes />
    </Layout>
  );
}

export default App;
