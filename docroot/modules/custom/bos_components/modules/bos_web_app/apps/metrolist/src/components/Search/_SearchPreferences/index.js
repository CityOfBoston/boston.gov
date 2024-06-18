import './SearchPreferences.scss';

import Checkbox from '@components/Checkbox';
import PropTypes from 'prop-types';
import React from 'react';
import Row from '@components/Row';
import { filtersObject } from '@util/validation';

function SearchPreferences( props ) {
  const householdIncome = localStorage.getItem( 'householdIncome' );
  const incomeRate = localStorage.getItem( 'incomeRate' );
  const useHouseholdIncomeAsIncomeQualificationFilter = ( localStorage.getItem( 'useHouseholdIncomeAsIncomeQualificationFilter' ) === 'true' );
  const hasEnteredHouseholdIncome = ( householdIncome && incomeRate );
  let householdIncomeRate;
  let abbreviatedHouseholdIncome;
  let incomeRateUnit;
  let abbreviatedIncomeRateUnit;

  if ( hasEnteredHouseholdIncome ) {
    const incomeRateLength = incomeRate.length;
    abbreviatedHouseholdIncome = householdIncome.substring( 0, householdIncome.length - 3 );
    incomeRateUnit = incomeRate.substring( 0, incomeRateLength - 2 );
    abbreviatedIncomeRateUnit = ( incomeRate === 'monthly' ) ? 'mo' : 'yr';
    householdIncomeRate = `${abbreviatedHouseholdIncome}/${abbreviatedIncomeRateUnit}.`;
  }

  const handleIncomeRestrictionToggle = ( event ) => {
    if ( !householdIncome ) {
      console.error( `localStorage.householdIncome not found; cannot apply minimum income filter` );
    }

    if ( !incomeRate ) {
      console.error( `localStorage.incomeRate not found; cannot apply minimum income filter` );
    }

    if ( !householdIncome || !incomeRate ) {
      return;
    }

    const { checked } = event.target;

    const householdIncomeNormalized = (
      ( parseInt( householdIncome.replace( /\D/g, '' ), 10 ) / 100 )
      * ( ( incomeRate === 'monthly' ) ? 12 : 1 )
    );

    const newFilters = {
      ...props.filters,
      "incomeQualification": {
        "upperBound": ( checked ? householdIncomeNormalized : null ),
      },
    };

    props.setFilters( newFilters );
    localStorage.setItem( 'useHouseholdIncomeAsIncomeQualificationFilter', checked.toString() );
  };

  return (
    hasEnteredHouseholdIncome
      ? (
      <menu
        data-testid="ml-search__preferences"
        className={ `ml-search__preferences ml-search-preferences${props.className ? ` ${props.className}` : ''}` }>
        <Row as="li" className="ml-search-preferences__hide-ineligible" space="1">
        {
          <Checkbox
            className="ml-search-preferences__hide-ineligible-checkbox"
            criterion="hideIneligibleIncomeRestrictedUnits"
            size="small"
            checked={ useHouseholdIncomeAsIncomeQualificationFilter }
            onChange={ handleIncomeRestrictionToggle }S
          >
            <span className="ml-search-preferences__hide-ineligible-text">
              Hide homes that require a household income over{ ' ' }
              <abbr className="ml-shorthand" title={ `${abbreviatedHouseholdIncome} per ${incomeRateUnit}` }>{ householdIncomeRate }</abbr>
            </span>
          </Checkbox>
        }
        </Row>
      </menu>
      )
      : null
  );
}

SearchPreferences.displayName = 'SearchPreferences';

SearchPreferences.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "filters": filtersObject,
  "setFilters": PropTypes.func.isRequired,
};

export default SearchPreferences;
