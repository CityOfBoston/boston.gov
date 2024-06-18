import React, {
  useEffect, useRef, forwardRef, useState,
} from 'react';
import PropTypes from 'prop-types';

import Icon from '@components/Icon';
import RadioGroup from '@components/RadioGroup';
import FormErrorMessage from '@components/FormErrorMessage';
import Stack from '@components/Stack';
import Required from '@components/Required';

import { updatePageTitle } from '@util/a11y-seo';
import { getIconId } from '../methods';

import './AmiEstimatorHouseholdSize.scss';

const AmiEstimatorHouseholdSize = forwardRef( ( props, ref ) => {
  const selfRef = ( ref || useRef() );
  const isRequired = true;
  const [familySizeIcon, setFamilySizeIcon] = useState( 'family2' );
  const handleHouseholdSizeChange = ( event ) => {
    const iconId = getIconId( event.target.value );
    setFamilySizeIcon( iconId );
  };

  useEffect( () => {
    updatePageTitle( 'Household Size', 'AMI Estimator' );
    props.setStep( props.step );
    props.adjustContainerHeight( selfRef );
  }, [] );

  return (
    <div
      ref={ selfRef }
      className="ml-ami-estimator__household-size ml-ami-estimator__prompt"
      data-testid="ml-ami-estimator__household-size"
      onChange={ handleHouseholdSizeChange }
    >
      <Stack as="fieldset" space="2" className="ml-ami-estimator__prompt-inner">
        <legend className="ml-ami-estimator__prompt-question">How many people live in your household of any age? { isRequired ? <Required /> : '' }</legend>
        <div className="ml-ami-estimator__prompt-answer">
          <Stack space="2">
            <Icon
              className="ml-ami-estimator__prompt-answer-icon"
              icon={ familySizeIcon }
              height="100"
              alt=""
            />
            <RadioGroup
              className={ `ml-ami-estimator__prompt--answer-input` }
              criterion="householdSize"
              values="1,2,3,4,5,6+"
              // units={ { "one": "person", "many": "people" } }
              // unitLabel={ { "type": "aria", "affix": "append" } }
              value={ props.formData.householdSize.value }
              aria-describedby="ami-estimator-household-size-error"
              required={ isRequired }
            />
          </Stack>
          <FormErrorMessage
            ref={ props.formData.householdSize.errorRef }
            id="ami-estimator-household-size-error"
            className="ml-ami-estimator__prompt-answer-error"
          >{ props.formData.householdSize.errorMessage }</FormErrorMessage>
        </div>
      </Stack>
    </div>
  );
} );

AmiEstimatorHouseholdSize.propTypes = {
  "step": PropTypes.number,
  "setStep": PropTypes.func,
  "children": PropTypes.node,
  "className": PropTypes.string,
  "formData": PropTypes.object,
  "adjustContainerHeight": PropTypes.func,
};

AmiEstimatorHouseholdSize.displayName = "HouseholdSize";

export default AmiEstimatorHouseholdSize;
