export function getNoFiltersApplied() { // eslint-disable-line import/prefer-default-export
  return {
    "offer": {
      "rent": false,
      "sale": false,
    },
    "location": {
      "city": {
        "boston": false,
        "beyondBoston": false,
      },
      "neighborhood": {
        "southBoston": false,
        "hydePark": false,
        "dorchester": false,
        "mattapan": false,
      },
      "cardinalDirection": {
        "west": false,
        "north": false,
        "south": false,
      },
    },
    "bedrooms": {
      "0": false,
      "1": false,
      "2": false,
      "3": false,
      "4+": false,
    },
    "amiQualification": {
      "lowerBound": 0,
      "upperBound": 200,
    },
    "incomeQualification": {
      "upperBound": null,
    },
    "rentalPrice": {
      "lowerBound": null,
      "upperBound": null,
    },
  };
}
