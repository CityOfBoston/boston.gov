import React from 'react';

import Inset from '@components/Inset';
import Logo from '@components/Logo';
import Tagline from '@components/Tagline';
import Stack from '@components/Stack';

import './AppHeader.scss';

function AppHeader() {
  return (
    <header className="ml-app-header">
      <Inset>
        <Stack as="hgroup" space="0.5" align={['middle']} className="ml-app-header__heading-container">
          <h1 className="ml-app-header__heading">
            <a className="ml-block" href="/metrolist/">
              <Logo width="145" />
            </a>
          </h1>
          <h2 className="ml-app-header__subheading" role="presentation">
            <Tagline />
          </h2>
        </Stack>
      </Inset>
    </header>
  );
}

export default AppHeader;
