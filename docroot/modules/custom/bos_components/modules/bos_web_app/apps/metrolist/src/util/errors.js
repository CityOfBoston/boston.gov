export function propTypeErrorMessage( { // eslint-disable-line import/prefer-default-export
  propName, componentName, got, expected, example,
} ) {
  let errorMessage = (
    `Invalid prop \`${propName}\` supplied to \`${componentName}\`.`
    + ` Got \`${got}\` (${typeof got});`
    + ` expected ${expected}`
  );

  if ( example ) {
    errorMessage += `, e.g. \`${example}\`.`;
  } else {
    errorMessage += '.';
  }

  return errorMessage;
}
