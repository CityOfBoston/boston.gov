import React from 'react';
import PropTypes from 'prop-types';

import UnitGroup from '@components/UnitGroup';
import HomeInfo from '@components/HomeInfo';
import Button from '@components/Button';
import Stack from '@components/Stack';
import Row from '@components/Row';

import { homeObject } from '@util/validation';
import { generateRandomNumberString } from '@util/strings';
import { isLiveDev } from '@util/dev';
import { isOnGoogleTranslate, copyGoogleTranslateParametersToNewUrl } from '@util/translation';
import { getGlobalThis } from '@util/objects';
import {
  serializeFiltersToUrlParams, renderJustListed, renderOffer, renderType,
} from './methods';

import './Home.scss';

const globalThis = getGlobalThis();

function Home( props ) {
  const { home, filters } = props;
  const {
    title,
    listingDate,
    incomeRestricted,
    applicationDueDate,
    assignment,
    city,
    neighborhood,
    type,
    units,
    offer,
    slug,
    // id,
    // url,
  } = home;

  let containsUnitWhereRentalPriceIsPercentageOfIncome = false;
  let percentageOfIncomeExplanationId = '';

  for ( let index = 0; index < units.length; index++ ) {
    const unit = units[index];

    if (
      ( unit.price === null )
      || ( unit.price === 'null' )
    ) {
      containsUnitWhereRentalPriceIsPercentageOfIncome = true;
      percentageOfIncomeExplanationId = `rental-price-percentage-income-explanation-${generateRandomNumberString()}`;
      break;
    }
  }

  const isBeingTranslated = isOnGoogleTranslate();
  let baseUrl;

  if ( isBeingTranslated ) {
    baseUrl = document.querySelector( 'base' ).getAttribute( 'href' ).replace( /\/metrolist\/.*/, '' );

    if ( isLiveDev( baseUrl ) ) {
      baseUrl = 'https://d8-dev.boston.gov';
    }
  } else if ( isLiveDev() ) {
    baseUrl = 'https://d8-dev.boston.gov';
  } else {
    baseUrl = globalThis.location.origin;
  }

  const relativePropertyPageUrl = `/metrolist/search/housing/${slug}/${serializeFiltersToUrlParams( filters, home )}`;
  const absolutePropertyPageUrl = `${baseUrl}${relativePropertyPageUrl}`;
  const propertyPageUrl = ( isBeingTranslated ? copyGoogleTranslateParametersToNewUrl( absolutePropertyPageUrl ) : absolutePropertyPageUrl );

  return (
    <article className="ml-home">
      <div className="ml-home__content">
        <Stack space="home-header">
          <header className="ml-home__header">
            <Stack space="home-header">
              <Row>
                <h4 className="ml-home__title" data-column-width="3/4">{ title }</h4>
                { renderJustListed( listingDate ) }
              </Row>
              <p className="ml-home__byline">{
                [city, neighborhood, renderOffer( offer ), renderType( type )]
                  .filter( ( item ) => !!item )
                  .join( ' – ' )
              }</p>
            </Stack>
          </header>
          <UnitGroup units={ units } percentageOfIncomeExplanationId={ percentageOfIncomeExplanationId } />
          {
            containsUnitWhereRentalPriceIsPercentageOfIncome
              && (
                <p id={ percentageOfIncomeExplanationId } className="ml-home__rental-price-percentage-income-explanation">
                  <span aria-hidden="true">**</span> Rent is determined by the administering agency based on household income.
                </p>
              )
          }
        </Stack>
        <Row as="footer" className="ml-home__footer" space="panel" stackUntil="small">{ /* TODO: Should be home-info--two-column */ }
          <HomeInfo
            className="ml-home-footer__home-info"
            info={ {
              listingDate,
              applicationDueDate,
              assignment,
              incomeRestricted,
            } }
          />
          <Button
            as="link"
            className="ml-home-footer__more-info-link"
            variant="primary"
            href={ propertyPageUrl }
            target={ isBeingTranslated ? '_blank' : undefined }
            aria-label={ `More info about ${title}` }
          >More info</Button>
        </Row>
      </div>
    </article>
  );
}

Home.propTypes = {
  "home": homeObject,
  "filters": PropTypes.object,
};

export default Home;
