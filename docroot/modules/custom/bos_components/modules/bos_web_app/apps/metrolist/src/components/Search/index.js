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
import Callout from '@components/Callout';
import FiltersPanel from '@components/FiltersPanel';
import { useHistory } from 'react-router-dom';

import Inset from '@components/Inset';
import PropTypes from 'prop-types';
import ResultsPanel from '@components/ResultsPanel';
import Row from '@components/Row';
import Stack from '@components/Stack';
import { getDevelopmentsApiEndpoint } from '@util/dev';
import SearchPreferences from './_SearchPreferences';
import SearchPagination from './_SearchPagination';
import ReactToPrint from "react-to-print";
import Button from '@components/Button';

import {
  paginate, useQuery, getPage, filterHomes, getNewFilters, filterHomesWithoutCounter
} from './methods';

const globalThis = getGlobalThis();
const apiEndpoint = getDevelopmentsApiEndpoint();

const defaultFilters = {
  "propertyName": {
    "keyphrase": "",
  },
  "offer": {
    "rent": true,
    "sale": false,
  },
  "location": {
    "cityType": {
      "boston": false,
      "beyondBoston": false,
    },
    "neighborhoodsInBoston": {},
    "citiesOutsideBoston": {}
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

    let savedNeighborhoods;
    
    if (savedFilters.location.neighborhood) {
      savedNeighborhoods = savedFilters.location.neighborhood;
    } else {
      savedNeighborhoods = savedFilters.location.neighborhoodsInBoston;
    }
    
    let savedNeighborhoodKeys = Object.keys( savedNeighborhoods );
    savedFilters.location.neighborhoodsInBoston = {};

    savedNeighborhoodKeys
      .filter((nb) => hasOwnProperty(defaultFilters.location.neighborhoodsInBoston, nb ) )
      .forEach( ( unavailableNeighborhood ) => {
        delete savedNeighborhoods[unavailableNeighborhood];
      } );
    savedNeighborhoodKeys = Object.keys( savedNeighborhoods );

    savedNeighborhoodKeys
      .sort()
      .forEach( ( nb ) => {
        savedFilters.location.neighborhoodsInBoston[nb] = savedNeighborhoods[nb];
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
  const printRef = useRef();
  const [filters, setFilters] = useState( props.filters );
  const [filteredAllHomes, setFilteredAllHomes] = useState(Object.freeze(props.homes));
  const [homesPerPage, setHomesPerPage] = useState(localStorage.getItem('homesPerPage') ? localStorage.getItem('homesPerPage') : 10)
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
  const [listingCounts, setListingCounts] = useState({
    "offer": {
      "rent": 0,
      "sale": 0,
    },
    "location": {
      "cityType": {
        "boston": 0,
        "beyondBoston": 0,
      },
      "neighborhoodsInBoston": {},
      "citiesOutsideBoston": {}
    },
  });

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
    const resetCities = {}

    Object.keys(filters.location.neighborhoodsInBoston )
      .sort()
      .forEach( ( nb ) => {
        resetNeighborhoods[nb] = false;
      } );
    
    Object.keys(filters.location.citiesOutsideBoston)
      .sort()
      .forEach((nb) => {
        resetCities[nb] = false;
      });

    // Unfortunately we have to do this manually rather than
    // doing `setFilters( defaultFilters )` because of a
    // “quantum entanglement” bug in React where `defaultFilters`
    // is modified along with `filters`, even if it was frozen beforehand.
    const resetFilters = {
      "propertyName": {
        "keyphrase": "",
      },
      "offer": {
        "rent": true,
        "sale": false,
      },
      "location": {
        "cityType": {
          "boston": false,
          "beyondBoston": false,
        },
        "neighborhoodsInBoston": {
          ...resetNeighborhoods,
        },
        "citiesOutsideBoston": {
          ...resetCities,
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

  const populateListingCounts = ( homes ) => {
    const emptyListingCount =  {
        "offer": {
          "rent": 0,
          "sale": 0,
        },
        "location": {
          "cityType": {
            "boston": 0,
            "beyondBoston": 0,
          },
          "neighborhoodsInBoston": {},
          "citiesOutsideBoston": {}
        },
        "rentalPrice": {
          "lowerBound": 0,
          "upperBound": 0,
        },
    };
    let updatedListingCounts = emptyListingCount;

    homes.forEach( ( home ) => {
      if ( home.offer === 'sale' ) {
        updatedListingCounts.offer.sale++;
      } else if ( home.offer === 'rent' ) {
        updatedListingCounts.offer.rent++;
      }
      if (home.city.toLowerCase() == 'boston' ) {
        // const neighborhoodKey = camelCase( home.neighborhood );
        const neighborhoodKey = home.neighborhood;
        updatedListingCounts.location.cityType.boston++;
        if (hasOwnProperty(updatedListingCounts.location.neighborhoodsInBoston, neighborhoodKey ) ) {
          updatedListingCounts.location.neighborhoodsInBoston[neighborhoodKey]++;
        } else {
          updatedListingCounts.location.neighborhoodsInBoston[neighborhoodKey] = 1;
        }
      } else if (home.city.toLowerCase() != 'boston') {
        const cityKey = home.city
        updatedListingCounts.location.cityType.beyondBoston++;
        if (hasOwnProperty(updatedListingCounts.location.citiesOutsideBoston, cityKey)) {
          updatedListingCounts.location.citiesOutsideBoston[cityKey]++;
        } else {
          updatedListingCounts.location.citiesOutsideBoston[cityKey] = 1;
        }
      }
    } );
    setListingCounts(updatedListingCounts)
  };

  const getAllHomes = () => {
    if ( paginatedHomes.length ) {
      return paginatedHomes.reduce( ( pageA, pageB ) => pageA.concat( pageB ) );
    }

    return [];
  };

  const loadData = ( newHomes ) => {
    const paginatedNewHomes = paginate( newHomes, homesPerPage );
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

    Object.keys(listingCounts.location.neighborhoodsInBoston )
      .sort()
      .forEach( ( nb ) => {
        newFilters.location.neighborhoodsInBoston[nb] = (newFilters.location.neighborhoodsInBoston[nb] || false );
        defaultFilters.location.neighborhoodsInBoston[nb] = false;
      } );

    Object.keys(listingCounts.location.citiesOutsideBoston)
      .sort()
      .forEach((nb) => {
        newFilters.location.citiesOutsideBoston[nb] = (newFilters.location.citiesOutsideBoston[nb] || false);
        defaultFilters.location.citiesOutsideBoston[nb] = false;
      });

    if (
      hasOwnProperty( savedFilters, 'location' )
      && hasOwnProperty( savedFilters.location, 'neighborhood' )
    ) {
      Object.keys(savedFilters.location.neighborhoodsInBoston )
        .forEach( ( nb ) => {
          if (!hasOwnProperty(defaultFilters.location.neighborhoodsInBoston, nb ) ) {
            delete savedFilters.location.neighborhoodsInBoston[nb];
          }
        } );
    }

    if (
      hasOwnProperty(savedFilters, 'location')
      && hasOwnProperty(savedFilters.location, 'cityType')
    ) {
      Object.keys(savedFilters.location.citiesOutsideBoston)
        .forEach((nb) => {
          if (!hasOwnProperty(defaultFilters.location.citiesOutsideBoston, nb)) {
            delete savedFilters.location.citiesOutsideBoston[nb];
          }
        });
    }

    if (Object.keys(newFilters.location.neighborhoodsInBoston).length===0) {
      const neighborhoods = [...new Set(newHomes
        .filter(listing => listing.city === "Boston")
        .map(listing => listing.neighborhood)
        .filter(Boolean))];

      const neighborhoodsInBoston = neighborhoods.reduce((acc, neighborhood) => {
        acc[neighborhood] = false;
        return acc;
      }, {});
      newFilters.location.neighborhoodsInBoston = neighborhoodsInBoston
    }

      if (Object.keys(newFilters.location.citiesOutsideBoston).length===0) {
      const cities = [...new Set(newHomes
        .filter(listing => listing.city !== "Boston")
        .map(listing => listing.city)
        .filter(Boolean))]
      const citiesOutsideBoston = cities.reduce((acc, city) => {
        acc[city] = false;
        return acc;
      }, {})
        newFilters.location.citiesOutsideBoston = citiesOutsideBoston
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

  const updateHomesPerPage = (event) => {
    const newHomesPerPage = event.target.value
    setHomesPerPage(newHomesPerPage)
    window.location.href = `?page=${Math.max(Math.round((currentPage - 1) * homesPerPage / newHomesPerPage), 1)}`;
    localStorage.setItem('homesPerPage', newHomesPerPage);
  }

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
    const filteredAllHomeWithoutCounter = filterHomesWithoutCounter({
      "homesToFilter": allHomes,
      "filtersToApply": filters,
      defaultFilters,
    })
    const paginatedFilteredHomes = paginate( filteredAllHomes, homesPerPage );
    const currentPageFilteredHomes = paginatedFilteredHomes[currentPage - 1];

    setFilteredHomes( currentPageFilteredHomes );
    setFilteredAllHomes( filteredAllHomes );
    setTotalPages( paginatedFilteredHomes.length );
    populateListingCounts(filteredAllHomeWithoutCounter);

    localStorage.setItem( 'filters', JSON.stringify( filters ) );
  }, [paginatedHomes, filters, currentPage, homesPerPage] );

  useEffect( () => {
    setPages( Array.from( { "length": totalPages }, ( v, k ) => k + 1 ) );
  }, [totalPages] );

  const supportsSvg = ( typeof SVGRect !== "undefined" );
  const FiltersPanelUi = () => {
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
    <article className={`ml-search${props.className ? ` ${props.className}` : ''}`}>
      <h2 className="sr-only">Search</h2>
      <Row space="panel" stackUntil="large">
        <Stack data-column-width="1/3" space="panel">
          <Row space="panel" stackUntil="small">
            <Stack data-column-width="1/3" space="panel">
              <SearchPreferences className="price-filter" filters={filters} setFilters={setFilters} />
            </Stack>
          </Row>
          { isDesktop ? SidebarUi : SidebarUi }
        </Stack>
        <Stack data-column-width="2/3" space="panel">
          <Row space="panel">
            <Stack data-column-width="2/3" className="ml-homes-per-page-stack" space="panel">
              <Row space="panel">
                <span className="ml-homes-per-page-label">
                  Homes Per Page: 
                </span>
                <select
                  id="homes-per-page-select"
                  name="select homes per page"
                  className="ml-filters-offer-type-select"
                  onChange={updateHomesPerPage}
                  value={homesPerPage}
                >
                  <option value={10}>{10}</option>
                  <option value={20}>{20}</option>
                  <option value={50}>{50}</option>
                  <option value={100}>{100}</option>
                  <option value={1000}>Show All Results</option>
                </select>
              </Row>
            </Stack>
            <Stack data-column-width="1/3" className="ml-print-button-stack" space="panel">
              <ReactToPrint
                trigger={() =>
                  <div className="print-button">
                    <Button variant="primary">Print Results</Button>
                  </div>}
                content={() => printRef.current} />
            </Stack>
          </Row>
          <ResultsPanel
            className="ml-search__results"
            columnWidth="2/3"
            filters={filters}
            homes={filteredHomes}
            homesHaveLoaded={homesHaveLoaded}
          />
        </Stack>
      </Row>

      {/* Hidden on screen but used for printing with allHomes */}
      <div className="print-only" ref={printRef}>
        <Row space="panel" stackUntil="large">
          <ResultsPanel
            className="ml-search__results"
            columnWidth="2/3"
            filters={filters}
            homes={filteredAllHomes}
            homesHaveLoaded={homesHaveLoaded}
          />
        </Row>
      </div>

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
