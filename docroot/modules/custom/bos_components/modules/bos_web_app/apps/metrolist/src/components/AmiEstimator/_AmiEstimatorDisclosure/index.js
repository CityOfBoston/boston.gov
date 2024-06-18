import React, {
  useEffect, useRef, forwardRef,
} from 'react';
import PropTypes from 'prop-types';

import Stack from '@components/Stack';
import Checkbox from '@components/Checkbox';
import FormErrorMessage from '@components/FormErrorMessage';
import Required from '@components/Required';

import { updatePageTitle } from '@util/a11y-seo';
import InputSummary from '../_AmiEstimatorInputSummary';

import './AmiEstimatorDisclosure.scss';

const AmiEstimatorDisclosure = forwardRef( ( props, ref ) => {
  const selfRef = ( ref || useRef() );
  const isRequired = true;

  useEffect( () => {
    updatePageTitle( 'Disclosure', 'AMI Estimator' );
    props.setStep( props.step );
    props.adjustContainerHeight( selfRef );
  }, [] );

  return (
    <div ref={ selfRef } className={ `ml-ami-estimator__disclosure ml-ami-estimator__prompt${props.className ? ` ${props.className}` : ''}` } data-testid="ml-ami-estimator__disclosure">
      <Stack space="2" className="ml-ami-estimator__prompt-inner">
        <InputSummary formData={ props.formData } />
        <p>The above information will be combined to estimate your eligibility for income-restricted housing. Eligibility is officially and finally determined during the application process.</p>
        <Stack space="1">
          <Checkbox
            className="ml-ami-estimator__disclosure-accept"
            criterion="disclosure"
            aria-describedby="ami-estimator-disclosure-accept-error"
            checked={ props.formData.disclosure.value }
            required={ isRequired }
          >
            I have read and understand the above statement.{ isRequired ? <Required /> : '' }
          </Checkbox>
          <FormErrorMessage
            ref={ props.formData.disclosure.errorRef }
            id="ami-estimator-disclosure-accept-error"
            className="ml-ami-estimator__prompt-answer-error"
          >{ props.formData.disclosure.errorMessage }</FormErrorMessage>
        </Stack>
      </Stack>
    </div>
  );
} );

AmiEstimatorDisclosure.displayName = 'Disclosure';

AmiEstimatorDisclosure.propTypes = {
  "step": PropTypes.number,
  "setStep": PropTypes.func,
  "children": PropTypes.node,
  "className": PropTypes.string,
  "formData": PropTypes.object,
  "adjustContainerHeight": PropTypes.func,
};

export default AmiEstimatorDisclosure;
