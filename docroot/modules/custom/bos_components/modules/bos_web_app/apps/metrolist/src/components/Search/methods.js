import { useLocation } from 'react-router-dom';
import { hasOwnProperty } from '@util/objects';
import levenshtein from 'js-levenshtein';
import { filter } from 'lodash';

// https://stackoverflow.com/a/11764168/214325
export function paginate(homes, homesPerPage) {
  const pages = [];
  const numberOfHomes = homes.length;
  const homesPerPageNumber = parseInt(homesPerPage, 10); // Ensure homesPerPage is a number
  for (let i = 0; i < numberOfHomes; i += homesPerPageNumber) {
    const page = homes.slice(i, i + homesPerPageNumber);
    pages.push(page);
  }
  return pages;
}

export function getQuery( location ) {
  return new URLSearchParams( location.search );
}

export function useQuery() {
  return new URLSearchParams( useLocation().search );
}

export function getPage( location ) {
  return parseInt( getQuery( location ).get( 'page' ), 10 );
}

export function filterHomes( {
  homesToFilter,
  filtersToApply,
  defaultFilters,
  matchOnNoneSelected = true,
} ) {
  const matchingHomes = homesToFilter
    .filter( ( home ) => {
      let keyphrase = filtersToApply.propertyName.keyphrase;
      function isMatch(target) {
        const FUZZY_SEARCH_THRESHOLD = 3
        const FUZZY_SEARCH_LENIENCE = 2
        const FUZZY_SEARCH_DIFF_MULTIPLIER = 3
        if (!target) {
          return false
        } else {
          let isIncludedInTarget = target.toLowerCase().includes(keyphrase.toLowerCase());
          let levDistance = levenshtein(target.toLowerCase(), keyphrase.toLowerCase());
          let wordCountDiff = Math.max((target.length - keyphrase.length), (keyphrase.length - target.length)) / target.length * FUZZY_SEARCH_DIFF_MULTIPLIER;
          return (isIncludedInTarget || ((FUZZY_SEARCH_THRESHOLD < keyphrase.length) && (FUZZY_SEARCH_THRESHOLD < target.length) && (levDistance <= wordCountDiff + FUZZY_SEARCH_LENIENCE)))
        }
      }

      let matchesPropertyName = (
        !keyphrase ||
        (
          isMatch(home.title) ||
          isMatch(home.city) ||
          isMatch(home.neighborhood)
        )
      );

      let matchesOffer = (
        (
          ( filtersToApply.offer.rent !== false )
          && ( home.offer === 'rent' )
        )
        || (
          ( filtersToApply.offer.sale !== false )
          && ( home.offer === 'sale' )
        )
      );

      let matchesBroadLocation = (
        (
          (filtersToApply.location.cityType.boston !== false )
          && ( home.city.toLowerCase() === 'boston' )
        )
        || (
          (filtersToApply.location.cityType.beyondBoston !== false )
          && ( home.city.toLowerCase() !== 'boston' )
        )
      );

      const neighborhoodInBostonIsPresentInFilters = (
        hasOwnProperty(filtersToApply.location.neighborhoodsInBoston, home.neighborhood )
        && (filtersToApply.location.neighborhoodsInBoston[home.neighborhood] === true )
      );
      const cityOutsideBostonIsPresentInFilters = (
        hasOwnProperty(filtersToApply.location.citiesOutsideBoston, home.city )
        && (filtersToApply.location.citiesOutsideBoston[home.city] === true )
      );
      
      let matchesNarrowLocation = ( home.city.toLowerCase() == 'boston' ) ? neighborhoodInBostonIsPresentInFilters : cityOutsideBostonIsPresentInFilters;

      const unitBedroomSizes = home.units.filter( ( unit ) => unit && unit.bedrooms ).map( ( unit ) => unit.bedrooms ).sort();
      let matchesBedrooms = unitBedroomSizes.length && (
        (
          ( filtersToApply.bedrooms['0'] === true )
          && ( unitBedroomSizes.indexOf( 0 ) !== -1 )
        )
        || (
          ( filtersToApply.bedrooms['1'] === true )
          && ( unitBedroomSizes.indexOf( 1 ) !== -1 )
        )
        || (
          ( filtersToApply.bedrooms['2'] === true )
          && ( unitBedroomSizes.indexOf( 2 ) !== -1 )
        )
        || (
          ( filtersToApply.bedrooms['3+'] === true )
          && ( unitBedroomSizes[unitBedroomSizes.length - 1] >= 3 )
        )
      );

      const dedupedAmi = new Set( home.units.filter( ( unit ) => unit && unit.amiQualification ).map( ( unit ) => unit.amiQualification ) );
      const unitAmiQualifications = Array.from( dedupedAmi );
      let matchesAmiQualification;

      if (
        ( home.incomeRestricted === false )
        || ( !unitAmiQualifications.length )
      ) {
        matchesAmiQualification = true;
      } else {
        for ( let index = 0; index < unitAmiQualifications.length; index++ ) {
          const amiQualification = ( unitAmiQualifications[index] || null );

          if ( amiQualification === null ) {
            matchesAmiQualification = true;
            break;
          }

          if ( filtersToApply.amiQualification.lowerBound <= filtersToApply.amiQualification.upperBound ) {
            matchesAmiQualification = (
              ( amiQualification >= filtersToApply.amiQualification.lowerBound )
              && ( amiQualification <= filtersToApply.amiQualification.upperBound )
            );
          // These values can be switched in the UI causing the names to no longer be semantic
          } else if ( filtersToApply.amiQualification.lowerBound > filtersToApply.amiQualification.upperBound ) {
            matchesAmiQualification = (
              ( amiQualification >= filtersToApply.amiQualification.upperBound )
              && ( amiQualification <= filtersToApply.amiQualification.lowerBound )
            );
          }

          if ( matchesAmiQualification ) {
            break;
          }
        }
      }

      if ( matchOnNoneSelected ) {
        if ( !filtersToApply.offer.rent && !filtersToApply.offer.sale ) {
          matchesOffer = true;
        }

        if (!filtersToApply.location.cityType.boston && !filtersToApply.location.cityType.beyondBoston ) {
          matchesBroadLocation = true;
          matchesNarrowLocation = true;
        }

        if (
          !filtersToApply.bedrooms['0']
          && !filtersToApply.bedrooms['1']
          && !filtersToApply.bedrooms['2']
          && !filtersToApply.bedrooms['3+']
        ) {
          matchesBedrooms = true;
        }
      }

      return (
        matchesPropertyName
        && matchesOffer
        && matchesBroadLocation
        && matchesNarrowLocation
        && matchesBedrooms
        && matchesAmiQualification
      );
    } )
    .map( ( home ) => {
      const newUnits = home.units
        .filter( ( unit ) => unit ) // Remove undefined, null, etc.
        .filter( ( unit ) => {
            let unitMatchesRentalPrice;
            let rentalPriceLowerBound;
            let rentalPriceUpperBound;

            // console.log('filtersToApply.rentalPrice.lowerBound', filtersToApply.rentalPrice.lowerBound);
            // console.log('filtersToApply.rentalPrice.upperBound', filtersToApply.rentalPrice.upperBound);

            if (isNaN(filtersToApply.rentalPrice.lowerBound) || filtersToApply.rentalPrice.lowerBound == null) {
              rentalPriceLowerBound = 0;
            } else {
              rentalPriceLowerBound = filtersToApply.rentalPrice.lowerBound;
            }

            if (isNaN(filtersToApply.rentalPrice.upperBound) || filtersToApply.rentalPrice.upperBound == null) {
              rentalPriceUpperBound = 10000000;
            } else {
              rentalPriceUpperBound = filtersToApply.rentalPrice.upperBound;
            }

            // @@Debugging - print comparing variables
            // console.log('rentalPriceLowerBound)',rentalPriceLowerBound);
            // console.log('unit.price',unit.price);
            // console.log('rentalPriceUpperBound',rentalPriceUpperBound);

            if ((unit.price >= rentalPriceLowerBound)  && (unit.price <= rentalPriceUpperBound) ) {
              unitMatchesRentalPrice = true;
            } else {
              unitMatchesRentalPrice = false;
            }
            //console.log('unitMatchesRentalPrice', unitMatchesRentalPrice);

          let unitMatchesBedrooms = (
            (
              filtersToApply.bedrooms['0br']
              && ( unit.bedrooms === 0 )
            )
            || (
              filtersToApply.bedrooms['1br']
              && ( unit.bedrooms === 1 )
            )
            || (
              filtersToApply.bedrooms['2br']
              && ( unit.bedrooms === 2 )
            )
            || (
              filtersToApply.bedrooms['3+br']
              && ( unit.bedrooms >= 3 )
            )
          );

          if ( matchOnNoneSelected ) {
            if (
              !filtersToApply.bedrooms['0br']
              && !filtersToApply.bedrooms['1br']
              && !filtersToApply.bedrooms['2br']
              && !filtersToApply.bedrooms['3+br']
            ) {
              unitMatchesBedrooms = true;
            }
          }

          // TODO: Maybe exit early if we already know it is not a match?
          // if ( !unitMatchesBedrooms ) {
          //   return false;
          // }

          let unitMatchesAmiQualification;
          const unitAmiQualification = ( unit.amiQualification || null );

          if ( unitAmiQualification === null ) {
            unitMatchesAmiQualification = true;
          } else if ( filtersToApply.amiQualification.lowerBound <= filtersToApply.amiQualification.upperBound ) {
            unitMatchesAmiQualification = (
              ( unitAmiQualification >= filtersToApply.amiQualification.lowerBound )
              && ( unitAmiQualification <= filtersToApply.amiQualification.upperBound )
            );
          // These values can be switched in the UI causing the names to no longer be semantic
          } else if ( filtersToApply.amiQualification.lowerBound > filtersToApply.amiQualification.upperBound ) {
            unitMatchesAmiQualification = (
              ( unitAmiQualification >= filtersToApply.amiQualification.upperBound )
              && ( unitAmiQualification <= filtersToApply.amiQualification.lowerBound )
            );
          }

          let unitMatchesIncomeQualification;
          const unitIncomeQualification = ( unit.incomeQualification || null );

          if ( ( unitIncomeQualification === null ) || !filtersToApply.incomeQualification.upperBound ) {
            unitMatchesIncomeQualification = true;
          } else {
            unitMatchesIncomeQualification = ( unitIncomeQualification <= filtersToApply.incomeQualification.upperBound );
          }

          return ( unitMatchesRentalPrice && unitMatchesBedrooms && unitMatchesAmiQualification && unitMatchesIncomeQualification );
        } );

      return {
        ...home,
        "units": newUnits,
      };
    } )
    .filter( ( home ) => !!home.units.length );

  return matchingHomes;
}

export function filterHomesWithoutCounter({
  homesToFilter,
  filtersToApply,
  defaultFilters,
  matchOnNoneSelected = true,
}) {
  const matchingHomes = homesToFilter
    .filter((home) => {
      let keyphrase = filtersToApply.propertyName.keyphrase;
      function isMatch(target) {
        const FUZZY_SEARCH_THRESHOLD = 3
        const FUZZY_SEARCH_LENIENCE = 2
        const FUZZY_SEARCH_DIFF_MULTIPLIER = 3
        if (!target) {
          return false
        } else {
          let isIncludedInTarget = target.toLowerCase().includes(keyphrase.toLowerCase());
          let levDistance = levenshtein(target.toLowerCase(), keyphrase.toLowerCase());
          let wordCountDiff = Math.max((target.length - keyphrase.length), (keyphrase.length - target.length)) / target.length * FUZZY_SEARCH_DIFF_MULTIPLIER;
          return (isIncludedInTarget || ((FUZZY_SEARCH_THRESHOLD < keyphrase.length) && (FUZZY_SEARCH_THRESHOLD < target.length) && (levDistance <= wordCountDiff + FUZZY_SEARCH_LENIENCE)))
        }
      }

      let matchesPropertyName = (
        !keyphrase ||
        (
          isMatch(home.title) ||
          isMatch(home.city) ||
          isMatch(home.neighborhood)
        )
      );

      let matchesOffer = (
        (
          (filtersToApply.offer.rent !== false)
          && (home.offer === 'rent')
        )
        || (
          (filtersToApply.offer.sale !== false)
          && (home.offer === 'sale')
        )
      );

      const unitBedroomSizes = home.units.filter((unit) => unit && unit.bedrooms).map((unit) => unit.bedrooms).sort();
      let matchesBedrooms = unitBedroomSizes.length && (
        (
          (filtersToApply.bedrooms['0'] === true)
          && (unitBedroomSizes.indexOf(0) !== -1)
        )
        || (
          (filtersToApply.bedrooms['1'] === true)
          && (unitBedroomSizes.indexOf(1) !== -1)
        )
        || (
          (filtersToApply.bedrooms['2'] === true)
          && (unitBedroomSizes.indexOf(2) !== -1)
        )
        || (
          (filtersToApply.bedrooms['3+'] === true)
          && (unitBedroomSizes[unitBedroomSizes.length - 1] >= 3)
        )
      );

      const dedupedAmi = new Set(home.units.filter((unit) => unit && unit.amiQualification).map((unit) => unit.amiQualification));
      const unitAmiQualifications = Array.from(dedupedAmi);
      let matchesAmiQualification;

      if (
        (home.incomeRestricted === false)
        || (!unitAmiQualifications.length)
      ) {
        matchesAmiQualification = true;
      } else {
        for (let index = 0; index < unitAmiQualifications.length; index++) {
          const amiQualification = (unitAmiQualifications[index] || null);

          if (amiQualification === null) {
            matchesAmiQualification = true;
            break;
          }

          if (filtersToApply.amiQualification.lowerBound <= filtersToApply.amiQualification.upperBound) {
            matchesAmiQualification = (
              (amiQualification >= filtersToApply.amiQualification.lowerBound)
              && (amiQualification <= filtersToApply.amiQualification.upperBound)
            );
            // These values can be switched in the UI causing the names to no longer be semantic
          } else if (filtersToApply.amiQualification.lowerBound > filtersToApply.amiQualification.upperBound) {
            matchesAmiQualification = (
              (amiQualification >= filtersToApply.amiQualification.upperBound)
              && (amiQualification <= filtersToApply.amiQualification.lowerBound)
            );
          }

          if (matchesAmiQualification) {
            break;
          }
        }
      }

      if (matchOnNoneSelected) {
        if (!filtersToApply.offer.rent && !filtersToApply.offer.sale) {
          matchesOffer = true;
        }

        if (
          !filtersToApply.bedrooms['0']
          && !filtersToApply.bedrooms['1']
          && !filtersToApply.bedrooms['2']
          && !filtersToApply.bedrooms['3+']
        ) {
          matchesBedrooms = true;
        }
      }

      return (
        matchesPropertyName
        && matchesOffer
        && matchesBedrooms
        && matchesAmiQualification
      );
    })
    .map((home) => {
      const newUnits = home.units
        .filter((unit) => unit) // Remove undefined, null, etc.
        .filter((unit) => {
          let unitMatchesRentalPrice;
          let rentalPriceLowerBound;
          let rentalPriceUpperBound;

          // console.log('filtersToApply.rentalPrice.lowerBound', filtersToApply.rentalPrice.lowerBound);
          // console.log('filtersToApply.rentalPrice.upperBound', filtersToApply.rentalPrice.upperBound);

          if (isNaN(filtersToApply.rentalPrice.lowerBound) || filtersToApply.rentalPrice.lowerBound == null) {
            rentalPriceLowerBound = 0;
          } else {
            rentalPriceLowerBound = filtersToApply.rentalPrice.lowerBound;
          }

          if (isNaN(filtersToApply.rentalPrice.upperBound) || filtersToApply.rentalPrice.upperBound == null) {
            rentalPriceUpperBound = 10000000;
          } else {
            rentalPriceUpperBound = filtersToApply.rentalPrice.upperBound;
          }

          // @@Debugging - print comparing variables
          // console.log('rentalPriceLowerBound)',rentalPriceLowerBound);
          // console.log('unit.price',unit.price);
          // console.log('rentalPriceUpperBound',rentalPriceUpperBound);

          if ((unit.price >= rentalPriceLowerBound) && (unit.price <= rentalPriceUpperBound)) {
            unitMatchesRentalPrice = true;
          } else {
            unitMatchesRentalPrice = false;
          }
          //console.log('unitMatchesRentalPrice', unitMatchesRentalPrice);

          let unitMatchesBedrooms = (
            (
              filtersToApply.bedrooms['0br']
              && (unit.bedrooms === 0)
            )
            || (
              filtersToApply.bedrooms['1br']
              && (unit.bedrooms === 1)
            )
            || (
              filtersToApply.bedrooms['2br']
              && (unit.bedrooms === 2)
            )
            || (
              filtersToApply.bedrooms['3+br']
              && (unit.bedrooms >= 3)
            )
          );

          if (matchOnNoneSelected) {
            if (
              !filtersToApply.bedrooms['0br']
              && !filtersToApply.bedrooms['1br']
              && !filtersToApply.bedrooms['2br']
              && !filtersToApply.bedrooms['3+br']
            ) {
              unitMatchesBedrooms = true;
            }
          }

          // TODO: Maybe exit early if we already know it is not a match?
          // if ( !unitMatchesBedrooms ) {
          //   return false;
          // }

          let unitMatchesAmiQualification;
          const unitAmiQualification = (unit.amiQualification || null);

          if (unitAmiQualification === null) {
            unitMatchesAmiQualification = true;
          } else if (filtersToApply.amiQualification.lowerBound <= filtersToApply.amiQualification.upperBound) {
            unitMatchesAmiQualification = (
              (unitAmiQualification >= filtersToApply.amiQualification.lowerBound)
              && (unitAmiQualification <= filtersToApply.amiQualification.upperBound)
            );
            // These values can be switched in the UI causing the names to no longer be semantic
          } else if (filtersToApply.amiQualification.lowerBound > filtersToApply.amiQualification.upperBound) {
            unitMatchesAmiQualification = (
              (unitAmiQualification >= filtersToApply.amiQualification.upperBound)
              && (unitAmiQualification <= filtersToApply.amiQualification.lowerBound)
            );
          }

          let unitMatchesIncomeQualification;
          const unitIncomeQualification = (unit.incomeQualification || null);

          if ((unitIncomeQualification === null) || !filtersToApply.incomeQualification.upperBound) {
            unitMatchesIncomeQualification = true;
          } else {
            unitMatchesIncomeQualification = (unitIncomeQualification <= filtersToApply.incomeQualification.upperBound);
          }

          return (unitMatchesRentalPrice && unitMatchesBedrooms && unitMatchesAmiQualification && unitMatchesIncomeQualification);
        });

      return {
        ...home,
        "units": newUnits,
      };
    })
    .filter((home) => !!home.units.length);

  return matchingHomes;
}

export function getNewFilters( event, filters ) {
  const $input = event.target;
  let newValue;
  const newFilters = { ...filters };
  let valueAsKey = false;
  let isNumeric = false;
  let specialCase = false;
  let parent;
  let parentCriterion;

  switch ( $input.type ) {
    case 'checkbox':
      newValue = $input.checked;
      valueAsKey = true;
      break;
    default:
      newValue = $input.value;
  }

  if ( hasOwnProperty( event, 'metrolist' ) ) {
    if ( hasOwnProperty( event.metrolist, 'parentCriterion' ) ) {
      parentCriterion = event.metrolist.parentCriterion;
      switch ( parentCriterion ) { // eslint-disable-line default-case
        case 'amiQualification':
        case 'rentalPrice':
          isNumeric = true;
          break;
      }

      if ( isNumeric ) {
        newValue = Number.parseInt( newValue, 10 );
      }

      if ( parentCriterion !== $input.name ) {
        if ( valueAsKey ) {
          specialCase = true;
          parent = newFilters[parentCriterion][$input.name];
          parent[$input.value] = newValue;
        } else {
          specialCase = true;
          parent = newFilters[parentCriterion];
          parent[$input.name] = newValue;
        }
      }
    }
  }

  if ( !specialCase ) {
    // console.log( '!specialCase' );
    parent = newFilters[$input.name];
    parent[$input.value] = newValue;
  }

  switch ( $input.name ) {
    case 'neighborhoodsInBoston':
      if (newValue && !filters.location.cityType.boston ) {
        newFilters.location.cityType.boston = newValue;
      }
      break;

    case 'citiesOutsideBoston':
      if (newValue && !filters.location.cityType.beyondBoston ) {
        newFilters.location.cityType.beyondBoston = newValue;
      }
      break;

    case 'bedrooms':
      newFilters.bedrooms[$input.value] = newValue;
      break;

    default:
  }

  // Selecting Boston or Beyond Boston checks/unchecks all subcategories
  switch ( $input.value ) {
    case 'boston':
      Object.keys( filters.location.neighborhoodsInBoston ).forEach( ( neighborhood ) => {
        newFilters.location.neighborhoodsInBoston[neighborhood] = newValue;
      } );
      break;

    case 'beyondBoston':
      Object.keys(filters.location.citiesOutsideBoston ).forEach(( city ) => {
        newFilters.location.citiesOutsideBoston[city] = newValue;
      } );
      break;

    default:
  }

  return newFilters;
}
