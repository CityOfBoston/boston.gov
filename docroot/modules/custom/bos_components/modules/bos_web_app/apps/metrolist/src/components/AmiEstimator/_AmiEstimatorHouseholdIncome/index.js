import React, {
  useEffect, useRef, forwardRef,
} from 'react';
import PropTypes from 'prop-types';

import Icon from '@components/Icon';
// import Checkbox from '@components/Checkbox';
import RadioGroup from '@components/RadioGroup';
// import Row from '@components/Row';
// import Column from '@components/Column';
import Stack from '@components/Stack';
import FormErrorMessage from '@components/FormErrorMessage';
import Required from '@components/Required';

import { updatePageTitle } from '@util/a11y-seo';
import { formatIncome } from '@util/strings';

import './AmiEstimatorHouseholdIncome.scss';

const AmiEstimatorHouseholdIncome = forwardRef( ( props, ref ) => {
  const selfRef = ( ref || useRef() );
  const incomeInputRef = useRef();
  // const incomeOutputRef = useRef();
  const defaultIncomeRate = 'Monthly';
  const isRequired = true;

  function setIncomeOutput( newValue ) {
    if ( newValue === '$0.00' ) {
      incomeInputRef.current.value = '';
    } else {
      incomeInputRef.current.value = newValue;
    }

    localStorage.setItem( 'householdIncome', newValue );
    localStorage.setItem( 'incomeRate', props.formData.incomeRate.value.toLowerCase() );
    localStorage.setItem( 'useHouseholdIncomeAsIncomeQualificationFilter', 'true' );
  }

  const handleIncomeChange = ( event ) => {
    const oldValue = event.target.value;
    const cursorStart = event.target.selectionStart;
    const cursorEnd = event.target.selectionEnd;
    const newValue = formatIncome( event.target.value );
    const difference = ( newValue.length - oldValue.length );
    const differenceAbsoluteValue = Math.abs( difference );

    setIncomeOutput( newValue );

    if ( Math.sign( difference ) < 1 ) {
      // http://dimafeldman.com/js/maintain-cursor-position-after-changing-an-input-value-programatically/
      event.target.setSelectionRange( cursorStart - differenceAbsoluteValue, cursorEnd - differenceAbsoluteValue );
    } else {
      event.target.setSelectionRange( cursorStart + differenceAbsoluteValue, cursorEnd + differenceAbsoluteValue );
    }
  };

  const handleIncomeRateChange = ( event ) => {
    localStorage.setItem( 'incomeRate', event.target.value.toLowerCase() );
  };

  useEffect( () => {
    updatePageTitle( 'Household Income', 'AMI Estimator' );
    props.setStep( props.step );
    props.adjustContainerHeight( selfRef );

    const initialAmount = props.formData.householdIncome.value;

    if ( initialAmount.length ) {
      const formattedInitialAmount = formatIncome( initialAmount );

      setIncomeOutput( formattedInitialAmount );
    }

    if ( !props.formData.incomeRate.value ) {
      const newFormData = {
        ...props.formData,
        "incomeRate": {
          ...props.formData.incomeRate,
          "value": defaultIncomeRate,
        },
      };

      props.setFormData( newFormData );
    }
  }, [] );

  return (
    <div ref={ selfRef } className={ `ml-ami-estimator__household-income ml-ami-estimator__prompt` } data-testid="ml-ami-estimator__household-income">
      <Stack as="fieldset" space="2" className="ml-ami-estimator__prompt-inner">
        <legend className="ml-ami-estimator__prompt-question">
          {
            ( parseInt( props.formData.householdSize.value, 10 ) === 1 ) // TODO: Should we event allow string values?
              ? <>What is your total income before taxes?</>
              : <>What is the total combined income of all { props.formData.householdSize.value ? `${props.formData.householdSize.value} people` : 'people' } who live in your household before taxes?</>
          }{ isRequired ? <Required /> : '' }
        </legend>
        <Stack space="2">
          <Icon className="ml-ami-estimator__prompt-answer-icon" icon="deposit_check" height="100" alt="" />
          <Stack space="1">{ /* ami-estimator-income-rate */ }
            <input
              id="household-income"
              ref={ incomeInputRef }
              className="ml-ami-estimator__household-income-input"
              name="householdIncome"
              value={ ( props.formData.householdIncome.value === '$0.00' ) ? '' : props.formData.householdIncome.value }
              aria-label={ props.formData.householdIncome.value }
              type="text"
              inputMode="numeric"
              pattern="[0-9]*"
              placeholder="$0.00"
              aria-describedby="ami-estimator-household-income-error"
              onChange={ handleIncomeChange }
              required={ isRequired }
              maxLength="20"
            />
            <FormErrorMessage
              ref={ props.formData.householdIncome.errorRef }
              id="ami-estimator-household-income-error"
              className="ml-ami-estimator__prompt-answer-error"
            >{ props.formData.householdIncome.errorMessage }</FormErrorMessage>
            <RadioGroup
              criterion="incomeRate"
              values="Yearly,Monthly"
              value={ props.formData.incomeRate.value || defaultIncomeRate }
              aria-label="income rate"
              required
              onChange={ handleIncomeRateChange }
            />
            <FormErrorMessage
              ref={ props.formData.incomeRate.errorRef }
              id="ami-estimator-household-income-rate-error"
              className="ml-ami-estimator__prompt-answer-error"
            >{ props.formData.incomeRate.errorMessage }</FormErrorMessage>
          </Stack>
        </Stack>
      </Stack>
    </div>
  );
} );

AmiEstimatorHouseholdIncome.propTypes = {
  "stepRef": PropTypes.object,
  "step": PropTypes.number,
  "setStep": PropTypes.func,
  "children": PropTypes.node,
  "className": PropTypes.string,
  "formData": PropTypes.object.isRequired,
  "setFormData": PropTypes.func.isRequired,
  "pathname": PropTypes.string,
  "adjustContainerHeight": PropTypes.func,
};

AmiEstimatorHouseholdIncome.displayName = "HouseholdIncome";

export default AmiEstimatorHouseholdIncome;
