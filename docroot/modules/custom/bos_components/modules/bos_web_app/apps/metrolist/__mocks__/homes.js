export const minimalHomeDefinition = {
  "title": "Home",
  "type": "apt",
  "offer": "rent",
  "listingDate": ( new Date() ).toISOString(),
  "units": [],
};

export const studioUnit = {
  "id": "studio",
  "bedrooms": 0,
  "price": 900,
  "priceRate": "monthly",
};

export const oneBedroomUnit = {
  "id": "1br",
  "bedrooms": 1,
  "price": 1000,
  "priceRate": "monthly",
};

export const twoBedroomUnit = {
  "id": "2br",
  "bedrooms": 2,
  "price": 2000,
  "priceRate": "monthly",
};

export const threeBedroomUnit = {
  "id": "3br",
  "bedrooms": 3,
  "price": 4000,
  "priceRate": "monthly",
};

export const aboveThreeBedroomUnit = {
  "id": "3+br",
  "bedrooms": 10,
  "price": 5000,
  "priceRate": "monthly",
};
