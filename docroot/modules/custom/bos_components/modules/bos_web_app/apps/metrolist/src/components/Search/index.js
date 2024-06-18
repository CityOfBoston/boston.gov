import './Search.scss';
import 'whatwg-fetch';

import React, { useEffect, useRef, useState } from 'react';
import {
  copyGoogleTranslateParametersToNewUrl,
  getUrlBeingTranslated,
  isOnGoogleTranslate,
} from '@util/translation';
import { filtersObject, homeObject } from '@util/validation';
import { getGlobalThis, hasOwnProperty, isPlainObject } from '@util/objects';
import { useHistory } from 'react-router-dom';

import Callout from '@components/Callout';
import FiltersPanel from '@components/FiltersPanel';
import Inset from '@components/Inset';
import PropTypes from 'prop-types';
import ResultsPanel from '@components/ResultsPanel';
import Row from '@components/Row';
import Stack from '@components/Stack';
import { getDevelopmentsApiEndpoint } from '@util/dev';
import SearchPreferences from './_SearchPreferences';
import SearchPagination from './_SearchPagination';

import {
  paginate, useQuery, getPage, filterHomes, getNewFilters,
} from './methods';

const globalThis = getGlobalThis();
const apiEndpoint = getDevelopmentsApiEndpoint();

const defaultFilters = {
  "offer": {
    "rent": true,
    "sale": false,
  },
  "location": {
    "city": {
      "boston": false,
      "beyondBoston": false,
    },
    "neighborhood": {},
    "cardinalDirection": {
      "west": false,
      "north": false,
      "south": false,
    },
  },
  "bedrooms": {
    "0br": false,
    "1br": false,
    "2br": false,
    "3+br": false,
  },
  "amiQualification": {
    "lowerBound": 0,
    "upperBound": 200,
  },
  "incomeQualification": {
    "upperBound": null,
  },
  "rentalPrice": {
    "lowerBound": 0,
    "upperBound": null,
  },
};
const defaultFilterKeys = Object.keys( defaultFilters );

let savedFilters = localStorage.getItem( 'filters' );
if ( savedFilters ) {
  savedFilters = JSON.parse( savedFilters );

  // Sanitize localStorage values that might arise from testing/old releases
  if ( isPlainObject( savedFilters ) ) {
    Object.keys( savedFilters )
      .filter( ( savedFilterKey ) => defaultFilterKeys.indexOf( savedFilterKey ) === -1 )
      .forEach( ( errantKey ) => {
        delete savedFilters[errantKey];
      } );

    const savedNeighborhoods = savedFilters.location.neighborhood;
    let savedNeighborhoodKeys = Object.keys( savedNeighborhoods );
    savedFilters.location.neighborhood = {};

    savedNeighborhoodKeys
      .filter( ( nb ) => hasOwnProperty( defaultFilters.location.neighborhood, nb ) )
      .forEach( ( unavailableNeighborhood ) => {
        delete savedNeighborhoods[unavailableNeighborhood];
      } );
    savedNeighborhoodKeys = Object.keys( savedNeighborhoods );

    savedNeighborhoodKeys
      .sort()
      .forEach( ( nb ) => {
        savedFilters.location.neighborhood[nb] = savedNeighborhoods[nb];
      } );

    if ( hasOwnProperty( savedFilters.bedrooms, '0' ) ) {
      savedFilters.bedrooms['0br'] = savedFilters.bedrooms['0'];
      delete savedFilters.bedrooms['0'];
    }

    if ( hasOwnProperty( savedFilters.bedrooms, '1' ) ) {
      savedFilters.bedrooms['1br'] = savedFilters.bedrooms['1'];
      delete savedFilters.bedrooms['1'];
    }

    if ( hasOwnProperty( savedFilters.bedrooms, '2' ) ) {
      savedFilters.bedrooms['2br'] = savedFilters.bedrooms['2'];
      delete savedFilters.bedrooms['2'];
    }

    if ( hasOwnProperty( savedFilters.bedrooms, '3' ) ) {
      savedFilters.bedrooms['3+br'] = savedFilters.bedrooms['3'];
      delete savedFilters.bedrooms['3'];
    }

    if ( hasOwnProperty( savedFilters.bedrooms, '3+' ) ) {
      savedFilters.bedrooms['3+br'] = savedFilters.bedrooms['3+'];
      delete savedFilters.bedrooms['3+'];
    }

    delete savedFilters.bedrooms['4+'];

    localStorage.setItem( 'filters', JSON.stringify( savedFilters ) );
  } else {
    console.log( 'isNotPlainObject' );
    savedFilters = {};
  }
} else {
  savedFilters = {};
}

let useAmiRecommendationAsLowerBound = localStorage.getItem( 'useAmiRecommendationAsLowerBound' );
let tempAMI;
if ( useAmiRecommendationAsLowerBound ) {
  useAmiRecommendationAsLowerBound = ( useAmiRecommendationAsLowerBound === 'true' );

  if ( useAmiRecommendationAsLowerBound ) {
    //savedFilters.amiQualification = ( savedFilters.amiQualification || { "lowerBound": 0, "upperBound": null } );
    //savedFilters.amiQualification.lowerBound = parseInt( localStorage.getItem( 'amiRecommendation' ), 10 );
    tempAMI = parseInt( localStorage.getItem( 'amiRecommendation' ), 10 );
  }
}

function Search( props ) {
  const [filters, setFilters] = useState( props.filters );
  const [paginatedHomes, setPaginatedHomes] = useState( paginate( Object.freeze( props.homes ) ) );
  const [filteredHomes, setFilteredHomes] = useState( Object.freeze( props.homes ) );
  const [currentPage, setCurrentPage] = useState( 1 );
  const [totalPages, setTotalPages] = useState( 1 );
  const [pages, setPages] = useState( [1] );
  const [isDesktop, setIsDesktop] = useState( window.matchMedia( '(min-width: 992px)' ).matches );
  const [showClearFiltersInitially, setShowClearFiltersInitially] = useState( false );
  const [homesHaveLoaded, setHomesHaveLoaded] = useState( false );
  const history = useHistory();
  const query = useQuery();
  const $drawer = useRef();
  let [updatingDrawerHeight, setUpdatingDrawerHeight] = useState( false ); // eslint-disable-line
  const isBeingTranslated = isOnGoogleTranslate();
  const baseUrl = ( isBeingTranslated ? getUrlBeingTranslated().replace( /\/metrolist\/.*/, '' ) : globalThis.location.origin );
  const relativeAmiEstimatorUrl = '/metrolist/ami-estimator';
  const absoluteAmiEstimatorUrl = `${baseUrl}${relativeAmiEstimatorUrl}`;
  const amiEstimatorUrl = ( isBeingTranslated ? copyGoogleTranslateParametersToNewUrl( absoluteAmiEstimatorUrl ) : relativeAmiEstimatorUrl );
  let listingCounts = {
    "offer": {
      "rent": 0,
      "sale": 0,
    },
    "location": {
      "city": {
        "boston": 0,
        "beyondBoston": 0,
      },
      "neighborhood": {},
      "cardinalDirection": {
        "west": 0,
        "north": 0,
        "south": 0,
      },
    },
  };

  history.listen( ( newLocation ) => {
    const requestedPage = getPage( newLocation );

    if ( requestedPage ) {
      setCurrentPage( requestedPage );
    } else {
      setCurrentPage( 1 );
    }
  } );

  const clearFilters = () => {
    const resetNeighborhoods = {};

    Object.keys( filters.location.neighborhood )
      .sort()
      .forEach( ( nb ) => {
        resetNeighborhoods[nb] = false;
      } );

    // Unfortunately we have to do this manually rather than
    // doing `setFilters( defaultFilters )` because of a
    // “quantum entanglement” bug in React where `defaultFilters`
    // is modified along with `filters`, even if it was frozen beforehand.
    const resetFilters = {
      "offer": {
        "rent": true,
        "sale": false,
      },
      "location": {
        "city": {
          "boston": false,
          "beyondBoston": false,
        },
        "neighborhood": {
          ...resetNeighborhoods,
        },
        "cardinalDirection": {
          "west": false,
          "north": false,
          "south": false,
        },
      },
      "bedrooms": {
        "0br": false,
        "1br": false,
        "2br": false,
        "3+br": false,
      },
      "amiQualification": {
        "lowerBound": 0,
        "upperBound": 200,
      },
      "incomeQualification": {
        "upperBound": null,
      },
      "rentalPrice": {
        "lowerBound": 0,
        "upperBound": null,
      },
    };

    // Save current filter state for undo functionality
    localStorage.setItem(
      'filters--undo',
      localStorage.getItem( 'filters' ),
    );
    localStorage.setItem(
      'useHouseholdIncomeAsIncomeQualificationFilter--undo',
      localStorage.getItem( 'useHouseholdIncomeAsIncomeQualificationFilter' ),
    );

    setFilters( resetFilters );
    localStorage.setItem( 'useHouseholdIncomeAsIncomeQualificationFilter', 'false' );
  };

  const undoClearFilters = () => {
    const filtersToRestore = JSON.parse( localStorage.getItem( 'filters--undo' ) );

    setFilters( filtersToRestore );

    localStorage.setItem(
      'useHouseholdIncomeAsIncomeQualificationFilter',
      localStorage.getItem( 'useHouseholdIncomeAsIncomeQualificationFilter--undo' ),
    );

    localStorage.removeItem( 'filters--undo' );
    localStorage.removeItem( 'useHouseholdIncomeAsIncomeQualificationFilter--undo' );
  };

  const clearListingCounts = () => {
    listingCounts = {
      "offer": {
        "rent": 0,
        "sale": 0,
      },
      "location": {
        "city": {
          "boston": 0,
          "beyondBoston": 0,
        },
        "neighborhood": {},
        "cardinalDirection": {
          "west": 0,
          "north": 0,
          "south": 0,
        },
      },
      "rentalPrice": {
        "lowerBound": 0,
        "upperBound": 0,
      },
    };
  };

  const populateListingCounts = ( homes ) => {
    clearListingCounts();

    homes.forEach( ( home ) => {
      if ( home.offer === 'sale' ) {
        listingCounts.offer.sale++;
      } else if ( home.offer === 'rent' ) {
        listingCounts.offer.rent++;
      }

      if ( home.city ) {
        if ( home.city.toLowerCase() === 'boston' ) {
          listingCounts.location.city.boston++;
        } else {
          listingCounts.location.city.beyondBoston++;
        }
      }

      if ( home.neighborhood ) {
        // const neighborhoodKey = camelCase( home.neighborhood );
        const neighborhoodKey = home.neighborhood;

        if ( hasOwnProperty( listingCounts.location.neighborhood, neighborhoodKey ) ) {
          listingCounts.location.neighborhood[neighborhoodKey]++;
        } else {
          listingCounts.location.neighborhood[neighborhoodKey] = 1;
        }
      } else if ( home.cardinalDirection ) {
        listingCounts.location.cardinalDirection[home.cardinalDirection]++;
      }

      // if ( Array.isArray( home.units ) ) {
      //   home.units.forEach( ( unit ) => {
      //     if ( home.offer === 'rent' ) {
      //       // Not extracting lowest rent since we can just default to $0 and let the user adjust

      //       if ( unit.price > listingCounts.rentalPrice.upperBound ) {
      //         listingCounts.rentalPrice.upperBound = unit.price;
      //       }
      //     }
      //   } );
      // }
    } );
  };

  const getAllHomes = () => {
    if ( paginatedHomes.length ) {
      return paginatedHomes.reduce( ( pageA, pageB ) => pageA.concat( pageB ) );
    }

    return [];
  };

  const loadData = ( newHomes ) => {
    const paginatedNewHomes = paginate( newHomes );
    populateListingCounts( newHomes );
    const existingFilters = localStorage.getItem( 'filters' );
    const requestedPage = parseInt( query.get( 'page' ), 10 );
    let newFilters;

    setPaginatedHomes( paginatedNewHomes );

    if ( requestedPage ) {
      setCurrentPage( requestedPage );
    } else {
      setCurrentPage( 1 );
    }

    setTotalPages( paginatedNewHomes.length );

    if ( existingFilters ) {
      newFilters = { ...JSON.parse( existingFilters ) };
    } else {
      newFilters = { ...filters };
    }

    if(useAmiRecommendationAsLowerBound === true){
      newFilters.amiQualification.lowerBound = tempAMI;
      localStorage.setItem( 'useAmiRecommendationAsLowerBound', 'false' )
    }

    Object.keys( listingCounts.location.neighborhood )
      .sort()
      .forEach( ( nb ) => {
        newFilters.location.neighborhood[nb] = ( newFilters.location.neighborhood[nb] || false );
        defaultFilters.location.neighborhood[nb] = false;
      } );

    Object.keys( listingCounts.location.cardinalDirection ).forEach( ( cd ) => {
      newFilters.location.cardinalDirection[cd] = ( newFilters.location.cardinalDirection[cd] || false );
      defaultFilters.location.cardinalDirection[cd] = false;
    } );

    if (
      hasOwnProperty( savedFilters, 'location' )
      && hasOwnProperty( savedFilters.location, 'neighborhood' )
    ) {
      Object.keys( savedFilters.location.neighborhood )
        .forEach( ( nb ) => {
          if ( !hasOwnProperty( defaultFilters.location.neighborhood, nb ) ) {
            delete savedFilters.location.neighborhood[nb];
          }
        } );
    }

    setFilters( newFilters );
    localStorage.setItem( 'filters', JSON.stringify( newFilters ) );

    const defaultFiltersString = JSON.stringify( defaultFilters, null, 2 );
    const savedFiltersString = JSON.stringify( savedFilters, null, 2 );

    const savedFiltersMatchDefaultFilters = (
      ( savedFiltersString !== '{}' )
      && ( defaultFiltersString === savedFiltersString )
    );

    setShowClearFiltersInitially( !savedFiltersMatchDefaultFilters );
    setHomesHaveLoaded( true );
  };

  const updateDrawerHeight = ( drawerRef, wait ) => {
    const updateHeight = () => {
      if ( drawerRef && drawerRef.current ) {
        const height = getComputedStyle( drawerRef.current ).getPropertyValue( 'height' );

        if ( height !== '0px' ) {
          drawerRef.current.style.height = height;
        }
      }

      setUpdatingDrawerHeight( false );
    };

    if ( wait ) {
      setTimeout( updateHeight, wait );
    } else {
      updateHeight();
    }
  };

  useEffect( () => {
    const allHomes = getAllHomes();

    if ( !allHomes.length ) {
      fetch(
        apiEndpoint,
        {
          "mode": "cors",
          "headers": {
            "Content-Type": "application/json",
          },
        },
      ) // TODO: CORS
        .then( async ( response ) => {
          if ( !response.body && !response._bodyInit ) {
            throw new Error( `Metrolist Developments API returned an invalid response.` );
          } else {
            return response.json();
          }
        } )
        .then( ( apiHomes ) => loadData( apiHomes ) )
        .catch( ( error ) => {
          console.error( error );
        } );
    } else {
      loadData( allHomes );
    }

    let isResizing = false;

    window.addEventListener( 'resize', ( /* event */ ) => {
      if ( !isResizing ) {
        isResizing = true;

        setTimeout( () => {
          setIsDesktop( window.matchMedia( '(min-width: 992px)' ).matches );
          isResizing = false;
        }, 125 );
      }
    } );
  }, [] );

  useEffect( () => {
    const allHomes = getAllHomes();

    if ( !allHomes.length ) {
      return;
    }

    const filteredAllHomes = filterHomes( {
      "homesToFilter": allHomes,
      "filtersToApply": filters,
      defaultFilters,
    } );
    const paginatedFilteredHomes = paginate( filteredAllHomes );
    const currentPageFilteredHomes = paginatedFilteredHomes[currentPage - 1];

    setFilteredHomes( currentPageFilteredHomes );
    setTotalPages( paginatedFilteredHomes.length );

    localStorage.setItem( 'filters', JSON.stringify( filters ) );
  }, [paginatedHomes, filters, currentPage] );

  useEffect( () => {
    setPages( Array.from( { "length": totalPages }, ( v, k ) => k + 1 ) );
  }, [totalPages] );

  const supportsSvg = ( typeof SVGRect !== "undefined" );
  const FiltersPanelUi = () => {
    populateListingCounts( getAllHomes() );

    return (
      <FiltersPanel
        key="filters-panel"
        className="ml-search__filters"
        drawerRef={ $drawer }
        filters={ filters }
        clearFilters={ clearFilters }
        undoClearFilters={ undoClearFilters }
        showClearFiltersInitially={ showClearFiltersInitially }
        listingCounts={ listingCounts }
        updateDrawerHeight={ updateDrawerHeight }
        updatingDrawerHeight={ updatingDrawerHeight }
        setUpdatingDrawerHeight={ setUpdatingDrawerHeight }
        handleFilterChange={ ( event ) => {
          const newFilters = getNewFilters( event, filters );
          setFilters( newFilters );
          setCurrentPage( 1 );
          localStorage.setItem( 'filters', JSON.stringify( newFilters ) );
        } }
      />
    );
  };
  const CalloutUi = (
    <Inset key="ami-estimator-callout" className="filters-panel__callout-container" until="large">
      <Callout
        className={ `${supportsSvg ? 'ml-callout--icon-visible ' : ''}filters-panel__callout` }
        as="a"
        href={ amiEstimatorUrl }
        target={ isBeingTranslated ? '_blank' : undefined }
      >
        <Callout.Heading as="span">Use our AMI Estimator to find homes that match your income</Callout.Heading>
        <Callout.Icon>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            version="1.1"
            className="ml-icon ml-icon--rightward-arrowhead"
            viewBox="0 0 10.842 18.615"
            width="11"
            height="19"
          >
            <title>&gt;</title>
            <path
              d="m0.93711 17.907c2.83-2.8267 5.66-5.6533 8.49-8.48-2.9067-2.9067-5.8133-5.8133-8.72-8.72"
              fill="none"
              stroke="currentColor"
              strokeMiterlimit="10"
              strokeWidth="2"
            ></path>
          </svg>
        </Callout.Icon>
      </Callout>
    </Inset>
  );
  const SidebarUi = [CalloutUi, FiltersPanelUi()];

  return (
    <article className={ `ml-search${props.className ? ` ${props.className}` : ''}` }>
      <h2 className="sr-only">Search</h2>
      <SearchPreferences filters={ filters } setFilters={ setFilters } />
      <Row space="panel" stackUntil="large">
        <Stack data-column-width="1/3" space="panel">
          { isDesktop ? SidebarUi : SidebarUi }
        </Stack>
        <ResultsPanel
          className="ml-search__results"
          columnWidth="2/3"
          filters={ filters }
          homes={ filteredHomes }
          homesHaveLoaded={ homesHaveLoaded }
        />
      </Row>
      <nav>
        <h3 className="sr-only">Pages</h3>
        <SearchPagination pages={ pages } currentPage={ currentPage } />
      </nav>
    </article>
  );
}

Search.propTypes = {
  "amiEstimation": PropTypes.number,
  "filters": filtersObject,
  "homes": PropTypes.arrayOf( homeObject ),
  "className": PropTypes.string,
};

Search.defaultProps = {
  "homes": [],
  "amiEstimation": null,
  "filters": {
    ...defaultFilters,
    // ...savedFilters,
  },
};

// localStorage.setItem( 'filters', JSON.stringify( Search.defaultProps.filters ) );

export default Search;
