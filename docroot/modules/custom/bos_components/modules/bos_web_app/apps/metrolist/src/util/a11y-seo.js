import { useLocation } from 'react-router-dom';
import { formatPageTitle } from '@util/strings';
import OnDemandLiveRegion from 'on-demand-live-region';
import { getGlobalThis } from '@util/objects';

const globalThis = getGlobalThis();

// Accessibility and Search Engine Optimization
export function updatePageTitle( pageTitle, sectionTitle ) {
  const formattedPageTitle = formatPageTitle( pageTitle, sectionTitle );
  const liveRegion = new OnDemandLiveRegion( {
    "level": 'assertive',
  } );

  document.title = formattedPageTitle;
  liveRegion.say( formattedPageTitle );
}

export function handlePseudoButtonKeyDown( event, triggerClick = false ) {
  if ( event.key === " " || event.key === "Enter" || event.key === "Spacebar" ) { // "Spacebar" for IE11 support
    // Prevent the default action to stop scrolling when space is pressed
    event.preventDefault();

    if ( triggerClick ) {
      event.target.click();
    }
  }
}
