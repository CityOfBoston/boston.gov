const { snakeCase, pascalCase } = require( 'change-case' );

function capitalize( text ) {
  return text.charAt( 0 ).toUpperCase() + text.slice( 1 );
}

function uncapitalize( text ) {
  return text.charAt( 0 ).toLowerCase() + text.slice( 1 );
}

function slugify( text ) {
  return snakeCase( text ).replace( /_/g, '-' );
}

function componentCase( text ) {
  return capitalize( pascalCase( text ) );
}

function generateRandomNumberString() {
  return Math.ceil( Math.random() * 1000000 ).toString();
}

module.exports = {
  capitalize,
  uncapitalize,
  slugify,
  componentCase,
  generateRandomNumberString,
};
