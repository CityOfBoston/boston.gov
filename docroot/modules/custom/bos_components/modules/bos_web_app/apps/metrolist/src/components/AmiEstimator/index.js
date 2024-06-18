import React, {
  useState, useRef, useEffect,
} from 'react';
import PropTypes from 'prop-types';
import {
  Switch,
  Route,
  useRouteMatch,
  withRouter,
  useLocation,
} from 'react-router-dom';
import {
  TransitionGroup,
  CSSTransition,
} from "react-transition-group";
// import { pascalCase } from 'change-case';

import { hasOwnProperty, getGlobalThis } from '@util/objects';
import { slugify, uncapitalize, componentCase } from '@util/strings';
import {
  resolveLocationConsideringGoogleTranslate,
  switchToGoogleTranslateBaseIfNeeded,
  switchBackToMetrolistBaseIfNeeded,
} from '@util/translation';
import { capitalCase } from 'change-case';

import Button from '@components/Button';
import ProgressBar from '@components/ProgressBar';
import Row from '@components/Row';
import Stack from '@components/Stack';
// import Alert from '@components/Alert';

import HouseholdSize from './_AmiEstimatorHouseholdSize';
import HouseholdIncome from './_AmiEstimatorHouseholdIncome';
import Disclosure from './_AmiEstimatorDisclosure';
import Result from './_AmiEstimatorResult';

import './AmiEstimator.scss';

const globalThis = getGlobalThis();

function badErrorMessageElementError( showHide = 'show/hide' ) {
  throw new Error(
    `Can’t ${showHide} UI error message: the value passed to \`${showHide}ErrorMessage\` is “${typeof $errorMessage}”;`
    + ` should be a DOM element or a React ref pointing to a DOM element. Check the state object and make sure your input has an \`errorRef\` property.`,
  );
}

function hideErrorMessage( $errorMessage ) {
  if ( $errorMessage ) {
    if ( hasOwnProperty( $errorMessage, 'current' ) ) {
      $errorMessage = $errorMessage.current;
    }

    if ( !$errorMessage ) {
      badErrorMessageElementError( 'hide' );
      return;
    }

    $errorMessage.classList.remove( '--visible' );

    setTimeout( () => {
      $errorMessage.hidden = true;
    }, 62.5 );
  } else {
    badErrorMessageElementError( 'hide' );
  }
}

function showErrorMessage( $errorMessage, index ) {
  if ( $errorMessage ) {
    if ( hasOwnProperty( $errorMessage, 'current' ) ) {
      $errorMessage = $errorMessage.current;
    }

    if ( !$errorMessage ) {
      badErrorMessageElementError( 'show' );
      return;
    }

    $errorMessage.hidden = false;

    setTimeout( () => {
      $errorMessage.classList.add( '--visible' );

      // console.log( 'index', index );

      if ( index === 0 ) {
        let $focusTarget;
        const $reverseLookup = document.querySelector( `[aria-describedby~=${$errorMessage.id}]` );

        if ( $reverseLookup ) {
          $focusTarget = $reverseLookup;
        } else {
          $focusTarget = $errorMessage;
        }

        $focusTarget.focus();
      }
    }, 62.5 );
  } else {
    badErrorMessageElementError( 'show' );
  }
}

function reportMissingValidityProperty( $formControl ) {
  throw new Error(
    `Form control `
      + `\`${$formControl.nodeName.toLowerCase()}`
      + `${$formControl.id && `#${$formControl.id}`}`
      + `${$formControl.className && `.${$formControl.className}`}\``
    + ` does not have the \`validity\` or \`checkValidity\` properties.`
    + ` This means either the current browser does not support HTML5 forms, or the React ref is misconfigured.`,
  );
}

function reportMissingDisplayNameProperty( index ) {
  throw new Error(
    `AMI Calculator step definition is incomplete:`
    + ` the object at \`AmiEstimator.steps[${index}]\` either needs a \`relativePath\` property,`
    + ` or its constituent ami-estimator needs to specify React’s \`displayName\` property.`,
  );
}

function getStepNumberFromPath( location, routePath, steps ) {
  const currentRelativePath = location.pathname.replace( routePath, '/' ).replace( '//', '/' );

  for ( let index = 0; index < steps.length; index++ ) {
    const currentStep = steps[index];
    let relativePath;

    if ( hasOwnProperty( currentStep, 'relativePath' ) ) {
      relativePath = currentStep.relativePath;
    } else if ( hasOwnProperty( currentStep.component, 'displayName' ) ) {
      relativePath = `/${slugify( currentStep.component.displayName )}`;
    } else {
      reportMissingDisplayNameProperty( index );
    }

    if ( relativePath === currentRelativePath ) {
      return ( index + 1 );
    }
  }

  throw new Error( `Cannot find step number for ${location.pathname}; please check the \`AmiEstimator.steps\` array.` );
}

function getErrors( $form, formData ) {
  const formValidity = $form.checkValidity();
  const newErrors = { ...formData };
  let numberOfErrors = 0;

  if ( !formValidity ) {
    const $elements = $form.elements;
    const radioButtons = {};

    for ( let index = 0; index < $elements.length; index++ ) {
      const $element = $elements[index];
      const { name } = $element;

      if ( hasOwnProperty( radioButtons, $element.name ) ) {
        break;
      }

      if ( 'validity' in $element ) {
        const { validity } = $element;

        if ( validity.valueMissing === true ) {
          if ( $element.type === 'radio' ) {
            radioButtons[name] = true;
          }

          newErrors[name] = {
            ...newErrors[name],
            "errorMessage": ( formData[name].errorMessageWhenRequired ? formData[name].errorMessageWhenRequired : "Please fill out this field." ),
          };

          numberOfErrors++;
        }
      } else {
        reportMissingValidityProperty( $element );
      } // if validity in $element
    } // for

    if ( numberOfErrors ) {
      newErrors.alert = {
        ...formData.alert,
        "errorMessage": "There were errors in your submission.",
      };
    }
  } else {
    Object.keys( formData ).forEach( ( errorName ) => {
      newErrors[errorName].errorMessage = '';
    } );
  }

  return [newErrors, numberOfErrors];
}

function handleSubmit( event ) {
  event.preventDefault();
}

function AmiEstimator( props ) {
  const { path } = useRouteMatch();
  const routerLocation = useLocation();
  const location = resolveLocationConsideringGoogleTranslate( routerLocation );
  const [heights, setHeights] = useState( {} );
  const [isNavigatingBackward, setIsNavigatingBackward] = useState( false );

  const noErrors = {
    "steps": [...props.steps],
    "householdSize": {
      "page": 1,
      "value": "",
      "errorMessage": "",
      "errorRef": useRef(),
      "errorMessageWhenRequired": "Please specify how many people live in your household.",
    },
    "householdIncome": {
      "page": 2,
      "value": "",
      "errorMessage": "",
      "errorRef": useRef(),
      "errorMessageWhenRequired": "Please specify your combined household income.",
    },
    "incomeRate": {
      "page": 2,
      "value": "",
      "errorMessage": "",
      "errorRef": useRef(),
      "errorMessageWhenRequired": "Please specify how often your combined household income recurs.",
    },
    "disclosure": {
      "page": 3,
      "value": "",
      "errorMessage": "",
      "errorRef": useRef(),
      "errorMessageWhenRequired": "Please agree to the disclosure before continuing.",
    },
    "amiEstimation": {
      "page": 4,
      "value": "",
    },
  };
  const [formData, setFormData] = useState( noErrors );
  const formRef = useRef();
  const currentStepRef = useRef();
  const totalSteps = props.steps.length;
  const [step, setStep] = useState( getStepNumberFromPath( location, path, props.steps ) );

  const getNextStepName = () => {
    const nextStep = ( step + 1 );
    const stepDefinition = props.steps[nextStep - 1];

    if ( stepDefinition ) {
      return capitalCase( stepDefinition.component.displayName );
    }

    return null;
  };

  const getNextStepPath = () => {
    const nextStep = ( step + 1 );
    const stepDefinition = props.steps[nextStep - 1];

    if ( nextStep <= props.steps.length ) {
      if ( stepDefinition === props.steps[0] ) {
        return path;
      }

      let relativePath = '';

      if ( hasOwnProperty( stepDefinition, 'relativePath' ) ) {
        relativePath = stepDefinition.relativePath;
      } else if ( hasOwnProperty( stepDefinition.component, 'displayName' ) ) {
        relativePath = `/${slugify( stepDefinition.component.displayName )}`;
      } else {
        reportMissingDisplayNameProperty( nextStep - 1 );
      }

      return `${path}${relativePath}`;
    }

    return null;
  };

  const getPreviousStepName = () => {
    const previousStep = ( step - 1 );
    const stepDefinition = props.steps[previousStep - 1];

    if ( stepDefinition ) {
      return capitalCase( stepDefinition.component.displayName );
    }

    return null;
  };

  const getPreviousStepPath = () => {
    const previousStep = ( step - 1 );
    const stepDefinition = props.steps[previousStep - 1];

    if ( previousStep >= 0 ) {
      if ( stepDefinition === props.steps[0] ) {
        return path;
      }

      return `${path}/${slugify( stepDefinition.component.displayName )}`;
    }

    return null;
  };

  const navigateForward = () => {
    const nextStepPath = getNextStepPath();
    const $base = document.querySelector( 'base[href]' );

    switchToGoogleTranslateBaseIfNeeded( $base );

    if ( nextStepPath !== null ) {
      props.history.push( nextStepPath );

      switchBackToMetrolistBaseIfNeeded( location, $base );
    } else {
      console.error( 'Can’t navigate forward' );
    }
  };

  const navigateBackward = () => {
    setIsNavigatingBackward( true );
    const previousStepPath = getPreviousStepPath();
    const $base = document.querySelector( 'base[href]' );

    switchToGoogleTranslateBaseIfNeeded( $base );

    if ( previousStepPath !== null ) {
      props.history.push( previousStepPath );

      switchBackToMetrolistBaseIfNeeded( location, $base );

      setTimeout( () => {
        setIsNavigatingBackward( false );
      }, 1000 );
    } else {
      console.error( 'Can’t navigate backward' );
    }
  };

  const clearErrors = ( errorNameList, newFormData = formData ) => {
    errorNameList.forEach( ( errorName ) => {
      try {
        hideErrorMessage( newFormData[errorName].errorRef );
      } catch ( exception ) {
        console.error( exception );
      }
    } );

    setFormData( newFormData );
  };

  const populateErrors = ( errorNameList, newFormData = formData ) => {
    errorNameList.forEach( ( errorName, index ) => {
      try {
        showErrorMessage( newFormData[errorName].errorRef, index );
      } catch ( exception ) {
        console.error( exception );
      }
    } );

    setFormData( newFormData );
  };

  const handleFormInteraction = ( event ) => {
    const navigatePrevious = event.target.hasAttribute( 'data-navigate-previous' );
    const navigateNext = event.target.hasAttribute( 'data-navigate-next' );

    if ( navigatePrevious ) {
      // clearAlert();
      navigateBackward();
    } else {
      const [newFormData, numberOfErrors] = getErrors( formRef.current, formData );
      const errorNameList = Object.keys( newFormData )
        .filter( ( formDataKey ) => (
          ( newFormData[formDataKey].page === step )
            || ( newFormData[formDataKey].page === 'all' )
        ) );

      if (
        ( event.type === 'change' )
        || ( event.type === 'keydown' )
      ) {
        if ( formRef.current ) {
          const { name } = event.target;

          Array.from( formRef.current.elements ).forEach( ( $element, index ) => {
            if ( hasOwnProperty( newFormData, name ) ) {
              if ( event.target.type === 'checkbox' ) {
                newFormData[name].value = event.target.checked;
              } else {
                newFormData[name].value = event.target.value;
              }
            } else {
              const nodeName = $element.nodeName.toLowerCase();
              const type = $element.getAttribute( 'type' );
              const { id, className } = $element;
              const elementName = $element.name;
              let reason;

              if ( !elementName ) {
                reason = `The form element \`${nodeName}${type ? `[type="${type}"]` : ''}${id ? `#${id}` : ''}${className ? `.${className.replace( /\s+/g, '.' )}` : ''}\` at \`formRef.current.elements[${index}]\` is missing the \`name\` attribute.`;
                console.warn( `Skipping state synchronization: ${reason}` );
              } else {
                reason = `The state object for AmiEstimator is missing a key named \`${name}\`.`;
                console.error( `Can’t synchronize React state with form state: ${reason}` );
              }
            }
          } );
        } else {
          console.error( new Error( `\`formRef.current\` returned falsy; can’t synchronize React state with form state. As a result, the AMI calculation will either be inaccurate or won’t work at all.` ) );
        }
      } // if change event

      if ( navigateNext && ( numberOfErrors > 0 ) ) {
        populateErrors( errorNameList, newFormData );
      } else {
        clearErrors( errorNameList, newFormData );

        if ( navigateNext ) {
          navigateForward();
        }
      }

      if ( navigateNext || navigatePrevious ) {
        event.preventDefault();
      }
    }
  };

  // let newHeights;
  let adjustContainerHeightInterval;
  let intervals = 0;
  const adjustContainerHeight = ( stepRef, timing = 1000 ) => {
    const breakIntervals = ( interval ) => {
      clearInterval( interval );
      intervals = 0;
    };

    adjustContainerHeightInterval = ( adjustContainerHeightInterval || setInterval( () => {
      stepRef = document.querySelector( '.step' );
      intervals++;

      if ( intervals >= 60 ) {
        breakIntervals( adjustContainerHeightInterval );
        return;
      }

      if ( stepRef ) {
        breakIntervals( adjustContainerHeightInterval );

        const $stepContent = stepRef.querySelector( '.ml-ami-estimator__prompt-inner' );
        const computedStyle = getComputedStyle( $stepContent ).getPropertyValue( 'height' );

        if ( computedStyle && ( computedStyle !== '0px' ) ) {
          const newHeights = {
            ...heights,
          };

          newHeights[location.pathname] = computedStyle;

          setHeights( newHeights );
        }
      }
    }, timing ) );
  };

  useEffect( () => {
    adjustContainerHeight( currentStepRef, 62.5 );
  }, [formData] );

  globalThis.addEventListener( 'resize', () => {
    adjustContainerHeight( currentStepRef, 62.5 );
  } );

  const nextStepName = getNextStepName();
  const previousStepName = getPreviousStepName();

  return (
    <Stack as="article" className={ `ml-ami-estimator${props.className ? ` ${props.className}` : ''}` } space="1" data-testid="ml-ami-estimator">
      <h2 className="sr-only">AMI Estimator</h2>
      <Stack as="header" space="1">
        <h3 className="sh-title ml-ami-estimator__heading">Find Housing Based on Your Income &amp; Household Size…</h3>
        <ProgressBar current={ step } total={ totalSteps } />
      </Stack>
      <form
        ref={ formRef }
        className="ami-estimator__form"
        onSubmit={ handleSubmit }
        onChange={ handleFormInteraction }
      >
        <Stack space="1">
          <TransitionGroup
            id="step"
            className="step"
            style={ {
              "height": heights[location.pathname],
            } }
          >
            { /*
              This is no different than other usage of
              <CSSTransition>, just make sure to pass
              `location` to `Switch` so it can match
              the old location as it animates out.
            */ }
            <CSSTransition
              key={ location.key }
              classNames={ isNavigatingBackward ? 'slide-right' : 'slide-left' }
              in={ true }
              appear={ false }
              mountOnEnter={ false }
              unmountOnExit={ false }
              timeout={ 900 }
            >
              <Switch location={ location }>
                {
                  props.steps.map( ( currentStep, index ) => {
                    const isFirstStep = ( index === 0 );
                    let displayName;

                    if ( hasOwnProperty( currentStep, 'relativePath' ) && ( currentStep.relativePath !== '/' ) ) {
                      displayName = componentCase( currentStep.relativePath );
                    } else if ( hasOwnProperty( currentStep.component, 'displayName' ) ) {
                      displayName = currentStep.component.displayName;
                    } else {
                      reportMissingDisplayNameProperty( index );
                    }

                    const formDataKey = uncapitalize( displayName );
                    const routePath = ( isFirstStep ? path : `${path}/${slugify( displayName )}` );

                    return (
                      <Route key={ formDataKey } exact={ isFirstStep } path={ routePath } render={ () => (
                        <currentStep.component
                          ref={ currentStepRef }
                          key={ formDataKey }
                          pathname={ location.pathname }
                          step={ index + 1 }
                          setStep={ setStep }
                          formData={ formData }
                          setFormData={ setFormData }
                          adjustContainerHeight={ adjustContainerHeight }
                        />
                      ) } />
                    );
                  } ) }
              </Switch>
            </CSSTransition>
          </TransitionGroup>
          <Row as="nav" className={ `ml-ami-estimator__navigation ml-ami-estimator__navigation--step-${step}` } onClick={ handleFormInteraction }>
            <Button
              className="ml-ami-estimator__button"
              type="button"
              disabled={ ( step === 1 ) }
              aria-label={ previousStepName && `Previous step: ${previousStepName}` }
              data-is-navigation-button
              data-navigate-previous
            >Back</Button>
            <Button
              className="ml-ami-estimator__button"
              type="button"
              variant="primary"
              disabled={ ( step === totalSteps ) }
              aria-label={ nextStepName && `Next step: ${nextStepName}` }
              data-is-navigation-button
              data-navigate-next
            >Next</Button>
          </Row>
        </Stack>
      </form>
      <Stack as="footer" className="ml-ami-estimator__footer" space="ami-estimator-footer">
        <p>
          <a
            className="ml-ami-estimator__email-link hide-form"
            href="mailto:metrolist@boston.gov"
          >
            For questions email <span className="ml-ami-estimator__email-link-email-address">metrolist@boston.gov</span>
          </a>
        </p>
      </Stack>
    </Stack>
  );
}

AmiEstimator.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "history": PropTypes.object.isRequired,
  "steps": PropTypes.arrayOf(
    PropTypes.shape( {
      "relativePath": PropTypes.string,
      "component": PropTypes.oneOfType( [PropTypes.func, PropTypes.object] ).isRequired,
    } ),
  ).isRequired,
};

AmiEstimator.defaultProps = {
  "steps": [
    {
      "relativePath": "/",
      "component": HouseholdSize,
    },
    {
      "relativePath": "/household-income",
      "component": HouseholdIncome,
    },
    {
      "relativePath": "/disclosure",
      "component": Disclosure,
    },
    {
      "relativePath": "/result",
      "component": Result,
    },
  ],
};

export default withRouter( AmiEstimator );
