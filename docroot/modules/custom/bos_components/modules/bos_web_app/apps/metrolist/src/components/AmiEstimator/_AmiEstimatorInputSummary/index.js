import React, { forwardRef } from 'react';
import PropTypes from 'prop-types';

import Row from '@components/Row';
import Icon from '@components/Icon';
import Stack from '@components/Stack';

import './AmiEstimatorInputSummary.scss';

import { getIconId } from '../methods';

/* istanbul ignore next */
function removeEmptyCents( money ) {
  const decimalPosition = ( money.length - 3 );

  if ( money.substring( decimalPosition ) === '.00' ) {
    return money.substring( 0, decimalPosition );
  }

  return money;
}

/* istanbul ignore next */
function formatIncome( income ) {
  if ( income ) {
    return removeEmptyCents( income );
  }

  return '$0.00';
}

/* istanbul ignore next */
function formatIncomeRate( incomeRate ) {
  if ( incomeRate ) {
    return incomeRate.toLowerCase().substring( 0, incomeRate.length - 2 );
  }

  return 'month';
}

const AmiEstimatorInputSummary = forwardRef( ( props, ref ) => (
  <Row ref={ ref } as="dl" className="ml-ami-estimator__input-summary" data-testid="ml-ami-estimator__input-summary" space="2" stackUntil="small">
    <Stack space="0.5" data-column-width="1/2">
      <Stack as="dt" space="1">
        <Icon
          className="ml-ami-estimator__prompt-answer-icon ml-ami-estimator__prompt-answer-icon--half"
          icon={ getIconId( props.formData.householdSize.value ) }
          height="100"
          alt=""
        />
        <span>Household:</span>
      </Stack>
      <dd>{ props.formData.householdSize.value || '0' }{ ( props.formData.householdSize.value === '1' ) ? ' person' : ' people' }</dd>
    </Stack>
    <Stack space="0.5" data-column-width="1/2">
      <Stack as="dt" space="1">
        <Icon
          className="ml-ami-estimator__prompt-answer-icon ml-ami-estimator__prompt-answer-icon--half"
          icon="deposit_check"
          height="100"
          alt=""
        />
        <span>Income:</span>
      </Stack>
      <dd>{ formatIncome( props.formData.householdIncome.value ) }/{ formatIncomeRate( props.formData.incomeRate.value ) }</dd>
    </Stack>
  </Row>
) );

AmiEstimatorInputSummary.displayName = 'InputSummary';

AmiEstimatorInputSummary.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "formData": PropTypes.object,
};

export default AmiEstimatorInputSummary;
