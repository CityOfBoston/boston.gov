import React from 'react';

export default function Logo( props ) {
  return (
    <picture>
      <source type="image/svg+xml" srcSet="/images/metrolist-logo.svg" />
      <img
        src="/images/metrolist-logo.png"
        srcSet="/images/metrolist-logo.png 1x, /images/metrolist-logo@2x.png 2x, /images/metrolist-logo@3x.png 3x"
        alt="Metrolist"
        { ...props }
      />
    </picture>
  );
}
