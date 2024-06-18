export function getIconId( householdSize ) { // eslint-disable-line import/prefer-default-export
  let iconId;

  switch ( householdSize ) {
    case '1':
      iconId = 'person';
      break;

    case '2':
      iconId = 'two-people';
      break;

    case '4':
      iconId = 'four-people';
      break;

    case '5':
      iconId = 'five-people';
      break;

    case '6+':
      iconId = 'six-people';
      break;

    case '3':
    default:
      iconId = 'family2';
  }

  return iconId;
}
