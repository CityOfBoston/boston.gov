import React, {
  useEffect, useRef, useState, forwardRef,
} from 'react';
import PropTypes from 'prop-types';

import Button from '@components/Button';
import Stack from '@components/Stack';

import { updatePageTitle } from '@util/a11y-seo';
import { isOnGoogleTranslate, copyGoogleTranslateParametersToNewUrl, getUrlBeingTranslated } from '@util/translation';
import { hasOwnProperty } from '@util/objects';

import InputSummary from '../_AmiEstimatorInputSummary';

import './AmiEstimatorResult.scss';

function getAmiDefintions() {
  let origin = 'https://www.boston.gov';
  if (typeof drupalSettings.cob.baseUrl !== "undefined") {
    origin = `https://${drupalSettings.cob.hostName}`;
  }
  const endpointPath = '/metrolist/api/v1/ami/hud/base';
  const endpoint = `${origin}${endpointPath}`;

  const request = new XMLHttpRequest();
  request.open("GET", endpoint, false);
  request.send(null);
  if (request.status !== 200) {
    console.warn(
      `Could not obtain current AMI values from endpoint.`
    );
    return;
  }
  return JSON.parse(request.responseText);

}
const amiDefinitions = getAmiDefintions();

function getAmiBracket( householdSize, annualizedHouseholdIncome ) {
  let bestMaxIncome = 0;
  let bestAmi = 0;

  for ( let index = 0; index < amiDefinitions.length; index++ ) {
    const amiBracket = amiDefinitions[index];

    if ( hasOwnProperty( amiBracket, 'maxIncomeByHouseholdSize' ) ) {
      const maxIncome = amiBracket.maxIncomeByHouseholdSize[householdSize - 1];
      if ( maxIncome >= annualizedHouseholdIncome ) {
        bestMaxIncome = maxIncome;
        bestAmi = amiBracket.ami;
        break;
      }
    } else {
      console.error( `Variable \`amiBracket\` (${typeof amiBracket}) is missing the property \`maxIncomeByHouseholdSize\`. Value:`, amiBracket );
    }
  }

  if (bestAmi == 0) {
      const lastItem = amiDefinitions.slice(-1)[0];
      bestAmi = lastItem.ami;
  }
  return { "ami": bestAmi, "maxIncome": bestMaxIncome };
}

function estimateAmi( { householdSize, householdIncome, incomeRate } ) {
  householdSize = householdSize.value.replace( '+', '' );
  householdIncome = householdIncome.value;
  incomeRate = incomeRate.value;

  const parsedHouseholdIncome = parseFloat( householdIncome.replace( /[$,]/g, '' ) );
  let annualizedHouseholdIncome = parsedHouseholdIncome;

  if ( incomeRate === 'Monthly' ) {
    annualizedHouseholdIncome *= 12;
  }

  if ( Number.isNaN( annualizedHouseholdIncome ) ) {
    console.warn(
      `AMI calculation failed:  \`annualizedHouseholdIncome\`  resolved to a non-numeric value.`
      + ` This could be due to \`props.formData\` being incomplete or missing.`,
    );

    return 0;
  }

  const amiBracket = getAmiBracket( householdSize, annualizedHouseholdIncome );

  return amiBracket.ami;
}

function isAboveUpperBound( amiEstimation ) {
  return ( amiEstimation > 200 );
}

const AmiEstimatorResult = forwardRef( ( props, ref ) => {
  const selfRef = ( ref || useRef() );
  const [amiEstimation, setAmiEstimation] = useState( 0 );
  const isBeingTranslated = isOnGoogleTranslate();

  localStorage.setItem( 'amiRecommendation', amiEstimation );
  localStorage.setItem( 'useAmiRecommendationAsLowerBound', 'true' );

  useEffect( () => {
    updatePageTitle( 'Result', 'AMI Estimator' );
    props.setStep( props.step );
    props.adjustContainerHeight( selfRef );

    const calculation = estimateAmi( {
      "amiDefinition": amiDefinitions,
      ...props.formData,
    } );

    setAmiEstimation( calculation );
  }, [] );

  useEffect( () => {
    localStorage.setItem( 'amiRecommendation', amiEstimation );
  }, [amiEstimation] );

  const metrolistSearchPath = '/metrolist/search';
  const urlBeingTranslated = getUrlBeingTranslated();
  const metrolistSearchUrl = ( isBeingTranslated ? copyGoogleTranslateParametersToNewUrl( urlBeingTranslated.replace( /\/metrolist\/.*/, metrolistSearchPath ) ) : metrolistSearchPath );

  return (
    <div ref={ selfRef } className={ `ml-ami-estimator__result ml-ami-estimator__prompt${props.className ? ` ${props.className}` : ''}` } data-testid="ml-ami-estimator__result">
      <Stack space="2" className="ml-ami-estimator__prompt-inner">
        <InputSummary formData={ props.formData } />
        <Stack space="1">
          { isAboveUpperBound( amiEstimation ) && <p>Given your income level, you are unlikely to qualify for units marketed on Metrolist.</p> }
          { !isAboveUpperBound( amiEstimation ) && <p>Given your income and household size, please search for homes listed at <b className="ml-ami">{ amiEstimation }% AMI</b> and above. Note that minimum income restrictions apply, and are listed in the unit details.</p> }
        </Stack>
        <Stack as="nav" space="1">
          <Button as="a" variant="primary" href={ metrolistSearchUrl } target={ isBeingTranslated ? '_blank' : undefined }>See homes that match this eligibility range</Button>
        </Stack>
      </Stack>
    </div>
  );
} );

AmiEstimatorResult.displayName = 'Result';

AmiEstimatorResult.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "step": PropTypes.number,
  "setStep": PropTypes.func,
  "formData": PropTypes.object,
  "fakeFormData": PropTypes.object,
  "adjustContainerHeight": PropTypes.func,
};

AmiEstimatorResult.defaultProps = {
  "fakeFormData": {
    "householdSize": {
      "value": "4",
    },
    "householdIncome": {
      "value": "$5,000.00",
    },
    "incomeRate": {
      "value": "Monthly",
    },
  },
};

export default AmiEstimatorResult;
