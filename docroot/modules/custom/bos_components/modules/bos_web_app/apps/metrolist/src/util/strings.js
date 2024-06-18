import { snakeCase, pascalCase } from 'change-case';

export function capitalize( text ) {
  return text.charAt( 0 ).toUpperCase() + text.slice( 1 );
}

export function uncapitalize( text ) {
  return text.charAt( 0 ).toLowerCase() + text.slice( 1 );
}

export function slugify( text ) {
  return snakeCase( text ).replace( /_/g, '-' );
}

export function componentCase( text ) {
  return capitalize( pascalCase( text ) );
}

export function generateRandomNumberString() {
  return Math.ceil( Math.random() * 1000000 ).toString();
}

// Examples:
// sectionTitle = AMI Estimator|Search
// pageTitle = Household Income
export function formatPageTitle( pageTitle, sectionTitle ) {
  let sectionPageTitle = '';

  if ( pageTitle && sectionTitle ) {
    sectionPageTitle = ` - ${sectionTitle}: ${pageTitle}`;
  } else if ( pageTitle && !sectionTitle ) {
    sectionPageTitle = ` - ${pageTitle}`;
  } else if ( sectionTitle && !pageTitle ) {
    sectionPageTitle = ` - ${sectionTitle}`;
  }

  return `${process.env.SITE_TITLE}${sectionPageTitle} | ${process.env.DOMAIN_TITLE}`;
}

export function pad( num, size ) {
  let s = `${num}`;
  while ( s.length < size ) s = `0${s}`;
  return s;
}

export function formatIncome( amount, includeCents = true ) {
  if ( typeof amount !== 'string' ) {
    amount = `${amount}`;
  }

  // Sanitize input
  amount = amount.trim().replace( /[^0-9]+/g, '' );
  let formatted;

  // If less than or equal to two digits long, attribute the digits to cents
  if ( amount.length <= 2 ) {
    if ( includeCents ) {
      formatted = `$0.${pad( amount, 2 )}`;
    } else {
      formatted = '$0';
    }
  // If greater than two digits long, attribute the remaining digits to dollars
  } else {
    const dollars = amount.substring( 0, amount.length - 2 );
    const dollarAmount = parseInt( dollars, 10 );
    let formattedDollars;

    if ( dollarAmount >= 1000 ) {
      // Add thousands separators
      // Via: https://blog.abelotech.com/posts/number-currency-formatting-javascript/
      formattedDollars = dollarAmount.toString().replace( /(\d)(?=(\d{3})+(?!\d))/g, '$1,' );
    } else {
      formattedDollars = dollarAmount.toString();
    }

    if ( includeCents ) {
      const cents = amount.substr( -2 );
      formatted = `$${formattedDollars}.${cents}`;
    } else {
      formatted = `$${formattedDollars}`;
    }
  }

  return formatted;
}
