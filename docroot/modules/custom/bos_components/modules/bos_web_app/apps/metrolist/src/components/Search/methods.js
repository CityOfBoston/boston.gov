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

// Helper function for fuzzy search match
function isMatch(target, keyphrase) {
  const FUZZY_SEARCH_THRESHOLD = 3;
  const FUZZY_SEARCH_LENIENCE = 2;
  const FUZZY_SEARCH_DIFF_MULTIPLIER = 3;

  if (!target) return false;

  const lowerTarget = target.toLowerCase();
  const lowerKeyphrase = keyphrase.toLowerCase();

  let isIncludedInTarget = lowerTarget.includes(lowerKeyphrase);
  let levDistance = levenshtein(lowerTarget, lowerKeyphrase);
  let wordCountDiff = Math.abs(target.length - keyphrase.length) / target.length * FUZZY_SEARCH_DIFF_MULTIPLIER;

  return isIncludedInTarget || (
    FUZZY_SEARCH_THRESHOLD < keyphrase.length &&
    FUZZY_SEARCH_THRESHOLD < target.length &&
    levDistance <= wordCountDiff + FUZZY_SEARCH_LENIENCE
  );
}

// Match property name
function matchPropertyName(home, filtersToApply, isNoneSelected) {
  const keyphrase = filtersToApply.propertyName.keyphrase;
  return !keyphrase || isMatch(home.title, keyphrase) || isMatch(home.city, keyphrase) || isMatch(home.neighborhood, keyphrase);
}

// Match offer type (rent/sale)
function matchOffer(home, filtersToApply, isNoneSelected) {
  if (isNoneSelected && !filtersToApply.offer.rent && !filtersToApply.offer.sale) return true;

  return (
    (filtersToApply.offer.rent !== false && home.offer === 'rent') ||
    (filtersToApply.offer.sale !== false && home.offer === 'sale')
  );
}

// Match broad and narrow locations
function matchLocation(home, filtersToApply, isNoneSelected) {
  if (isNoneSelected && !filtersToApply.location.cityType.boston && !filtersToApply.location.cityType.beyondBoston) return true;

  let matchesBroadLocation = (
    (filtersToApply.location.cityType.boston !== false && home.city.toLowerCase() === 'boston') ||
    (filtersToApply.location.cityType.beyondBoston !== false && home.city.toLowerCase() !== 'boston')
  );

  const matchesNarrowLocation = home.city.toLowerCase() === 'boston' ?
    hasOwnProperty(filtersToApply.location.neighborhoodsInBoston, home.neighborhood) && filtersToApply.location.neighborhoodsInBoston[home.neighborhood] === true :
    hasOwnProperty(filtersToApply.location.citiesOutsideBoston, home.city) && filtersToApply.location.citiesOutsideBoston[home.city] === true;

  return matchesBroadLocation && matchesNarrowLocation;
}

// Match bedrooms
function matchBedrooms(home, filtersToApply, isNoneSelected) {
  if (isNoneSelected && !filtersToApply.bedrooms['0'] && !filtersToApply.bedrooms['1'] && !filtersToApply.bedrooms['2'] && !filtersToApply.bedrooms['3+']) return true; 
  const unitBedroomSizes = home.units.filter(unit => unit && unit.bedrooms).map(unit => unit.bedrooms).sort();
  return unitBedroomSizes.length && (
    (filtersToApply.bedrooms['0'] === true && unitBedroomSizes.indexOf(0) !== -1) ||
    (filtersToApply.bedrooms['1'] === true && unitBedroomSizes.indexOf(1) !== -1) ||
    (filtersToApply.bedrooms['2'] === true && unitBedroomSizes.indexOf(2) !== -1) ||
    (filtersToApply.bedrooms['3+'] === true && unitBedroomSizes[unitBedroomSizes.length - 1] >= 3)
  );
}

// Match ADA accessibility
function matchAda(home, filtersToApply, isNoneSelected) {
  if (isNoneSelected && !filtersToApply.ada['ada-m'] && !filtersToApply.ada['ada-h']) return true;
  const unitAdaMInfo = home.units.some(unit => unit && unit["ada-m"] === 'true');
  const unitAdaHInfo = home.units.some(unit => unit && unit["ada-h"] === 'true');

  return (!filtersToApply.ada["ada-m"] || unitAdaMInfo) 
        && (!filtersToApply.ada["ada-h"] || unitAdaHInfo);
}

// Match amenities
function matchAmenities(home, filtersToApply, isNoneSelected) {
  const allAmenitiesFalse = Object.values(filtersToApply.amenities).every(value => value === false);
  if (isNoneSelected && allAmenitiesFalse) return true;
  const features = String(home.features).split(",").map(feature => feature.trim());
  return features.length 
    && Object.keys(filtersToApply.amenities)
      .filter(amenity => filtersToApply.amenities[amenity])
      .every(amenity => features.includes(amenity));
}

// Match AMI qualifications
function matchAmiQualification(home, filtersToApply, isNoneSelected) {
  const dedupedAmi = new Set(home.units.filter(unit => unit && unit.amiQualification).map(unit => unit.amiQualification));
  const unitAmiQualifications = Array.from(dedupedAmi);

  if (!home.incomeRestricted || !unitAmiQualifications.length) return true;

  return unitAmiQualifications.some(amiQualification => {
    if (amiQualification === null) return true;

    if (filtersToApply.amiQualification.lowerBound <= filtersToApply.amiQualification.upperBound) {
      return amiQualification >= filtersToApply.amiQualification.lowerBound &&
             amiQualification <= filtersToApply.amiQualification.upperBound;
    } else {
      return amiQualification >= filtersToApply.amiQualification.upperBound &&
             amiQualification <= filtersToApply.amiQualification.lowerBound;
    }
  });
}

// Match App Type
function matchAppType(home, filtersToApply, isNoneSelected) {
  if (isNoneSelected && !filtersToApply.appType.first && !filtersToApply.appType.lottery && !filtersToApply.appType.waitlist) return true;

  return (
    (filtersToApply.appType.first !== false && home.assignment === 'first') ||
    (filtersToApply.appType.lottery !== false && home.assignment === 'lottery') ||
    (filtersToApply.appType.waitlist !== false && home.assignment === 'waitlist')
  );
}


// Helper function to match unit rental price
function unitMatchesRentalPrice(unit, filtersToApply) {
  const rentalPriceLowerBound = filtersToApply.rentalPrice.lowerBound || 0;
  const rentalPriceUpperBound = filtersToApply.rentalPrice.upperBound || 10000000;

  return unit.price >= rentalPriceLowerBound && unit.price <= rentalPriceUpperBound;
}

// Helper function to match unit bedrooms
function unitMatchesBedrooms(unit, filtersToApply, matchOnNoneSelected) {
  let matchesBedrooms = (
    (filtersToApply.bedrooms['0br'] && unit.bedrooms === 0) ||
    (filtersToApply.bedrooms['1br'] && unit.bedrooms === 1) ||
    (filtersToApply.bedrooms['2br'] && unit.bedrooms === 2) ||
    (filtersToApply.bedrooms['3+br'] && unit.bedrooms >= 3)
  );

  if (matchOnNoneSelected) {
    if (!filtersToApply.bedrooms['0br'] && !filtersToApply.bedrooms['1br'] && !filtersToApply.bedrooms['2br'] && !filtersToApply.bedrooms['3+br']) {
      matchesBedrooms = true;
    }
  }

  return matchesBedrooms;
}

// Helper function to match unit ADA accessibility
function unitMatchesAda(unit, filtersToApply, matchOnNoneSelected) {
  if (matchOnNoneSelected && !filtersToApply.ada["ada-m"] && !filtersToApply.ada["ada-h"]) {
    return true
  }
  return (!filtersToApply.ada["ada-m"] || unit["ada-m"] === "true") 
        && (!filtersToApply.ada["ada-h"] || unit["ada-h"] === "true");
}

// Helper function to match unit AMI qualification
function unitMatchesAmiQualification(unit, home, filtersToApply) {
  return matchAmiQualification(home, filtersToApply);
}

// Helper function to match unit income qualification
function unitMatchesIncomeQualification(unit, filtersToApply) {
  return unit.incomeQualification === null ||
    filtersToApply.incomeQualification.upperBound === null ||
    unit.incomeQualification <= filtersToApply.incomeQualification.upperBound;
}

// Helper function to filter each unit within a home
function filterUnits(home, filtersToApply, matchOnNoneSelected) {
  return home.units.filter(unit => {
    let matchesRentalPrice = unitMatchesRentalPrice(unit, filtersToApply);
    let matchesBedrooms = unitMatchesBedrooms(unit, filtersToApply, matchOnNoneSelected);
    let matchesAda = unitMatchesAda(unit, filtersToApply, matchOnNoneSelected);
    let matchesAmiQualification = unitMatchesAmiQualification(unit, home, filtersToApply);
    let matchesIncomeQualification = unitMatchesIncomeQualification(unit, filtersToApply);

    return matchesRentalPrice && matchesBedrooms && matchesAda && matchesAmiQualification && matchesIncomeQualification;
  });
}

// Main filterHomes function
export function filterHomes({
  homesToFilter,
  filtersToApply,
  defaultFilters,
  matchOnNoneSelected = true,
}) {
  const matchingHomes = homesToFilter
    .filter(home => {
      let propertyMatches = matchPropertyName(home, filtersToApply, matchOnNoneSelected);
      let offerMatches = matchOffer(home, filtersToApply, matchOnNoneSelected);
      let locationMatches = matchLocation(home, filtersToApply, matchOnNoneSelected);
      let bedroomsMatch = matchBedrooms(home, filtersToApply, matchOnNoneSelected);
      let adaMatches = matchAda(home, filtersToApply, matchOnNoneSelected);
      let amenitiesMatch = matchAmenities(home, filtersToApply, matchOnNoneSelected);
      let amiQualificationMatches = matchAmiQualification(home, filtersToApply, matchOnNoneSelected);
      let appTypeMatches = matchAppType(home, filtersToApply, matchOnNoneSelected);
      return (
        propertyMatches &&
        offerMatches &&
        locationMatches &&
        bedroomsMatch &&
        adaMatches &&
        amenitiesMatch &&
        amiQualificationMatches &&
        appTypeMatches
      );
    })
    .map(home => {
      const newUnits = filterUnits(home, filtersToApply, matchOnNoneSelected);
      return { ...home, units: newUnits };
    })
    .filter(home => !!home.units.length);

  return matchingHomes;
}


export function filterHomesWithoutCounter({
  homesToFilter,
  filtersToApply,
  defaultFilters,
  matchOnNoneSelected = true,
}) {
  const matchingHomes = homesToFilter
    .filter(home => {
      let propertyMatches = matchPropertyName(home, filtersToApply, matchOnNoneSelected);
      let offerMatches = matchOffer(home, filtersToApply, matchOnNoneSelected);
      let bedroomsMatch = matchBedrooms(home, filtersToApply, matchOnNoneSelected);
      let adaMatches = matchAda(home, filtersToApply, matchOnNoneSelected);
      let amenitiesMatch = matchAmenities(home, filtersToApply, matchOnNoneSelected);
      let amiQualificationMatches = matchAmiQualification(home, filtersToApply, matchOnNoneSelected);
      let appTypeMatches = matchAppType(home, filtersToApply, matchOnNoneSelected);
      return (
        propertyMatches &&
        offerMatches &&
        bedroomsMatch &&
        adaMatches &&
        amenitiesMatch &&
        amiQualificationMatches &&
        appTypeMatches
      );
    })
    .map(home => {
      const newUnits = filterUnits(home, filtersToApply, matchOnNoneSelected);
      return { ...home, units: newUnits };
    })
    .filter(home => !!home.units.length);

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
        case 'amenities':
          valueAsKey = false;
          break;
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
          if (Object.values(parent).every(value => !value)) {
            switch ($input.name) {
              case 'citiesOutsideBoston':
                filters.location.cityType.beyondBoston = false
                break;
              case 'neighborhoodsInBoston':
                filters.location.cityType.boston = false
                break;
            }
          }
        } else {
          specialCase = true;
          parent = newFilters[parentCriterion];
          parent[$input.name] = newValue;
        }
      }
    }
  }

  if ( !specialCase ) {
    switch ($input.name) {
      case 'lowerBound':
      case 'upperBound':
        parent = newFilters.amiQualification[$input.name] = Number.parseInt(newValue, 10)
        break;
      default:
      parent = newFilters[$input.name];
      parent[$input.value] = newValue;
      break;
        
    }
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
    
    case 'ada':
      newFilters.ada[$input.value] = newValue;
      break;
    
    case 'appType':
      newFilters.appType[$input.value] = newValue;
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
