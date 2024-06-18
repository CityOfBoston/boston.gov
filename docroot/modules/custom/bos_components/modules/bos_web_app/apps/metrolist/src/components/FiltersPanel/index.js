import React, {
  useState, useRef,
} from 'react';
import PropTypes from 'prop-types';
import { capitalCase } from 'change-case';

import { filtersObject } from '@util/validation';
import { handlePseudoButtonKeyDown } from '@util/a11y-seo';
import { getGlobalThis } from '@util/objects';

import FilterGroup from '@components/FilterGroup';
import FilterLabel from '@components/FilterLabel';
import Checkbox from '@components/Checkbox';
import Range from '@components/Range';
import RangeManual from '@components/RangeManual';
import Icon from '@components/Icon';
// import Inset from '@components/Inset';
import Row from '@components/Row';
import Column from '@components/Column';
import Stack from '@components/Stack';
import ClearFiltersButton from '@components/ClearFiltersButton';

import './FiltersPanel.scss';

const globalThis = getGlobalThis();

function handleDoubleClick( event ) {
  // https://stackoverflow.com/a/43321596/214325
  if ( event.detail > 1 ) { // Number of clicks
    event.preventDefault();
  }
}

function FiltersPanel( props ) {
  const isDesktop = true; // globalThis.matchMedia( '(min-width: 992px)' ).matches; // TODO: define breakpoints that line up with the CSS in JS somewhere
  const attributes = { ...props };
  const [isExpanded, setExpanded] = useState( isDesktop );
  const [hasInteractedWithFilters, setHasInteractedWithFilters] = useState( false );
  const [lastInteractedWithFilters, setLastInteractedWithFilters] = useState( null );
  const $self = useRef();

  const handleClick = ( event ) => {
    const $element = event.target;
    let { className, nodeName } = $element;

    nodeName = nodeName.toLowerCase();



    if ( nodeName === 'use' ) {
      className = $element.parentNode.className;
    }

    if ( className instanceof SVGAnimatedString ) {
      className = className.baseVal;
    }

    const isFiltersPanelClick = /\bml-filters-panel/.test( className );

    if ( isFiltersPanelClick ) {
      setExpanded( !isExpanded );
    } else {
      if ( !props.updatingDrawerHeight ) {
        props.setUpdatingDrawerHeight( true );
        props.drawerRef.current.style.height = '';
        props.updateDrawerHeight( props.drawerRef, 250 );
      }
    }

    // updateOwnHeight();


  };

  // useEffect( props.updateDrawerHeight );

  globalThis.addEventListener( 'resize', ( /* event */ ) => {
    if ( !props.updatingDrawerHeight ) {
      props.setUpdatingDrawerHeight( true );
      props.drawerRef.current.style.height = '';
      props.updateDrawerHeight( props.drawerRef, 125 );
    }
  }, false );

  if ( props.className ) {
    delete attributes.className;
  }

  if ( props.columnWidth ) {
    delete attributes.columnWidth;
    attributes['data-column-width'] = props.columnWidth;
  }

  if ( props.filters ) {
    delete attributes.filters;
  }

  if ( props.handleFilterChange ) {
    delete attributes.handleFilterChange;
  }

  delete attributes.updateDrawerHeight;
  delete attributes.updatingDrawerHeight;
  delete attributes.setUpdatingDrawerHeight;
  delete attributes.listingCounts;
  delete attributes.drawerRef;
  delete attributes.clearFilters;
  delete attributes.undoClearFilters;
  delete attributes.showClearFiltersInitially;

  const {
    offer,
    location,
    bedrooms,
    amiQualification,
    rentalPrice,
  } = props.filters;
  const { listingCounts } = props;
  //Replace with show and hide text
  const isExpandedIndicator = ( isExpanded ? '- hide' : '+ show' );
  const ariaLabel = `Filter Listings ${isExpandedIndicator}`;
  const rentalCount = listingCounts.offer.rent;
  const saleCount   = listingCounts.offer.sale;

  const storedChoice = localStorage.getItem( 'storedChoice' );
  const [choice, setChoice] = useState(storedChoice);
  function setOfferType(event) {

    setChoice(event.target.value);
    localStorage.setItem( 'storedChoice', event.target.value );

    if (event.target.value == 'rent') {
      props.filters.offer.rent = true;
      props.filters.offer.sale = false;
    } else {
      props.filters.offer.rent = false;
      props.filters.offer.sale = true;
    }
  }
  //console.log('choice', choice);
  //console.log('storedChoice', storedChoice);
  //Also moved offer types out of main menu

  return (
    <section
      data-testid="ml-filters-panel-offer-type"
      ref={ $self }
      className={
        `ml-filters-panel${
          props.className
            ? ` ${props.className}`
            : ''
        }${
          isExpanded ? ' ml-filters-panel--expanded' : ''
        }`
      }
      { ...attributes }
      onClick={ handleClick }
      onChange={ ( event ) => {
        props.handleFilterChange( event );
      } }
    >
    <div className="ml-filters-panel__menu">
      <FilterGroup criterion="offer">
        <FilterGroup.Label>Offer Type</FilterGroup.Label>
        <select
          id="offer-type-select"
          name="select change"
          className="ml-filters-offer-type-select"
          onChange={ setOfferType }
          value = {choice}
          defaultValue={"rent"}
        >
          <option value="rent">{ `Rent` }</option>
          <option value="sale">{ `Sale` }</option>
        </select>
        <div className="noShow" >
          <Row space="rent-sale" stackAt="large">
            <Column width="1/2">
              <Checkbox
                button
                criterion="offer"
                value="rent"
                checked={ offer.rent }
                aria-label={ `For Rent` }
              >{ `For Rent` }</Checkbox>
            </Column>
            <Column width="1/2">
              <Checkbox
                button
                criterion="offer"
                value="sale"
                checked={ offer.sale }
              >{ `For Sale` }</Checkbox>
            </Column>
          </Row>
        </div>
      </FilterGroup>
    </div>


    <section
      data-testid="ml-filters-panel"
      ref={ $self }
      className={
        `ml-filters-panel${
          props.className
            ? ` ${props.className}`
            : ''
        }${
          isExpanded ? ' ml-filters-panel--expanded' : ''
        }`
      }
      { ...attributes }
      onClick={ handleClick }
      onChange={ ( event ) => {
        setHasInteractedWithFilters( true );
        setLastInteractedWithFilters( Date.now() );

        props.handleFilterChange( event );
      } }
    >
      <div className="ml-filters-panel__menu">

        <h3
          className="ml-filters-panel__heading"
          aria-label={ ariaLabel }
          aria-expanded={ isExpanded.toString() }
          aria-controls="filters-panel-content"
          onMouseDown={ handleDoubleClick }
          onKeyDown={ ( event ) => handlePseudoButtonKeyDown( event, true ) }
          tabIndex="0"
        >
          Filters
          <span className="ml-filters-panel__heading-icon">{ isExpandedIndicator }</span>
        </h3>
        <div
          id="filters-panel-content"
          ref={ props.drawerRef }
          className={ `ml-filters-panel__content${isExpanded ? ' ml-filters-panel__content--expanded' : ' ml-filters-panel__content'}` }
        >
          <menu className="ml-filters-panel__clear">
            <li>
              <ClearFiltersButton
                clearFilters={ props.clearFilters }
                undoClearFilters={ props.undoClearFilters }
                showClearFiltersInitially={ props.showClearFiltersInitially }
                hasInteractedWithFilters={ hasInteractedWithFilters }
                lastInteractedWithFilters={ lastInteractedWithFilters }
              />
            </li>
          </menu>

          <FilterGroup criterion="rentalPrice">
            <FilterGroup.Label>Price</FilterGroup.Label>
            <RangeManual
              criterion="rentalPrice"
              step={ 100 }
              lowerBound={ rentalPrice.lowerBound }
              upperBound={ rentalPrice.upperBound }
              valueFormat="$"
              valueAppend={ () => <> </> }
              maxValueAppend={ () => <>+</> }
            />
          </FilterGroup>
          <FilterGroup criterion="location">
            <FilterGroup.Label>Location</FilterGroup.Label>
            <Stack space="sister-checkboxes">
              <Checkbox
                criterion="city"
                value="boston"
                checked={ location.city.boston }
                hasSubcategories
              >
                <FilterLabel>Boston</FilterLabel>
                {
                  Object.keys( listingCounts.location.neighborhood )
                    .sort()
                    .sort( ( neighborhoodA, neighborhoodB ) => (
                      listingCounts.location.neighborhood[neighborhoodB]
                        - listingCounts.location.neighborhood[neighborhoodA]
                    ) )
                    .map( ( neighborhood ) => {
                      const count = listingCounts.location.neighborhood[neighborhood];
                      return (
                        <Checkbox
                          key={ neighborhood }
                          criterion="neighborhood"
                          value={ neighborhood }
                          checked={ location.neighborhood[neighborhood] || false }
                        >{ `${capitalCase( neighborhood )}` }</Checkbox>
                      );
                    } )
                }
              </Checkbox>
              <Checkbox
                criterion="city"
                value="beyondBoston"
                checked={ location.city.beyondBoston }
                hasSubcategories
              >
                <FilterLabel>Beyond Boston</FilterLabel>
                {
                  Object.keys( listingCounts.location.cardinalDirection )
                    .sort( ( cardinalDirectionA, cardinalDirectionB ) => (
                      listingCounts.location.cardinalDirection[cardinalDirectionB]
                        - listingCounts.location.cardinalDirection[cardinalDirectionA]
                    ) )
                    .map( ( cardinalDirection ) => {
                      const count = listingCounts.location.cardinalDirection[cardinalDirection];
                      return (
                        <Checkbox
                          key={ cardinalDirection }
                          criterion="cardinalDirection"
                          value={ cardinalDirection }
                          checked={ location.cardinalDirection[cardinalDirection] || false }
                        >{ `${capitalCase( cardinalDirection )} of Boston` }</Checkbox>
                      );
                    } )
                }
              </Checkbox>
            </Stack>
          </FilterGroup>
          <FilterGroup criterion="bedrooms" orientation="horizontal">
            <FilterGroup.Label>Bedrooms</FilterGroup.Label>
            <Checkbox criterion="bedrooms" aria-label="0-bedrooms" checked={ !!bedrooms['0br'] } value="0br">0</Checkbox>
            <Checkbox criterion="bedrooms" aria-label="1-bedrooms" checked={ !!bedrooms['1br'] } value="1br">1</Checkbox>
            <Checkbox criterion="bedrooms" aria-label="2-bedrooms" checked={ !!bedrooms['2br'] } value="2br">2</Checkbox>
            <Checkbox criterion="bedrooms" aria-label="3+-bedrooms" checked={ !!bedrooms['3+br'] } value="3+br">3+</Checkbox>
          </FilterGroup>
          <FilterGroup criterion="amiQualification">
            <FilterGroup.Label>Income Eligibility</FilterGroup.Label>
            <div onClick={ ( event ) => event.stopPropagation() }>
              <Range
                criterion="amiQualification"
                min={ 0 }
                max={ 200 }
                lowerBound={ amiQualification.lowerBound }
                upperBound={ amiQualification.upperBound }
                valueFormat="%"
                valueAppend={ () => <>&nbsp;<abbr className="ml-range__review-unit">AMI</abbr></> }
              />
            </div>
          </FilterGroup>
        </div>
      </div>{ /* filters-p anel__menu */ }
    </section>
    </section>
  );
}

FiltersPanel.propTypes = {
  "className": PropTypes.string,
  "columnWidth": PropTypes.string,
  "filters": filtersObject,
  "clearFilters": PropTypes.func,
  "undoClearFilters": PropTypes.func,
  "showClearFiltersInitially": PropTypes.bool,
  "handleFilterChange": PropTypes.func.isRequired,
  "updateDrawerHeight": PropTypes.func.isRequired,
  "listingCounts": PropTypes.object,
  "drawerRef": PropTypes.object.isRequired,
  "updatingDrawerHeight": PropTypes.bool,
  "setUpdatingDrawerHeight": PropTypes.func,
  "isExpanded": PropTypes.bool,
  "isRental": PropTypes.bool,
};

export default FiltersPanel;
