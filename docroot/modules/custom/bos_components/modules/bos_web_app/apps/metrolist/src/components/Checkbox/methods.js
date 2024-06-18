/* eslint-disable react/prop-types */
import React from 'react';

import Reveal from '@components/Reveal';
import Row from '@components/Row';
import Stack from '@components/Stack';

export function renderText( text ) {
  return <span className="ml-checkbox__text">{ text }</span>;
}

export function toTextContent( children ) {
  let textContent = children;

  while (
    ( typeof textContent !== 'string' )
    && textContent.props
    && textContent.props.children
  ) {
    textContent = children.props.children;
  }

  if ( typeof textContent !== 'string' ) {
    if ( Array.isArray( textContent ) ) {
      textContent = textContent
        .map( ( node ) => {
          if ( typeof node === 'string' ) {
            return node;
          }

          const nodeTextContent = toTextContent( node );
          const forcedStringConversion = `${nodeTextContent}`;

          if ( forcedStringConversion.toLowerCase() === '[object object]' ) {
            return '';
          }

          return nodeTextContent;
        } )
        .join( '' );
    }
  }

  return textContent;
}

export function renderLabel( children, props ) {
  const { subcategoriesOnly } = props;
  let { value } = props;

  if ( subcategoriesOnly ) {
    return (
      <span className="ml-checkbox__label">
        { renderText( children ) }
      </span>
    );
  }

  if ( !value ) {
    if ( typeof children === 'string' ) {
      value = children.trim();
    }
  }

  const ariaLabel = ( props['aria-label'] || toTextContent( children ) );

  return (
    <label className="ml-checkbox__label" aria-label={ ariaLabel }>
      <Row className="ml-checkbox__label-content" space="panel" align="middle">
        <span className={ `ml-checkbox__form-control-container${props.size ? ` ml-checkbox__form-control-container--${props.size}` : ''}` }>
          <input
            className="ml-checkbox__form-control"
            type="checkbox"
            name={ props.criterion }
            value={ value }
            required={ props.required }
            checked={ props.checked }
            onChange={ props.onChange }
          />
          <span className="ml-checkbox__form-control-ui"></span>
        </span>
        { renderText( children ) }
      </Row>
    </label>
  );
}

export function renderChoices( props ) {
  const {
    children, hasSubcategories,
  } = props; // eslint-disable-line react/prop-types

  switch ( hasSubcategories ) {
    case false:
      return renderLabel( children, props );

    case true: {
      let firstRenderedChoice;
      const childArray = React.Children.toArray( children );
      const firstChild = childArray.shift();

      if ( firstChild.type && firstChild.type.displayName === 'FilterLabel' ) {
        firstRenderedChoice = renderLabel( firstChild, props );
      } else {
        childArray.unshift( firstChild );
      }

      return (
        <Stack space="subcategories">
          { firstRenderedChoice }
          <Reveal
            className="ml-checkbox__subcategories"
            stack={ { "space": "1", "indent": ( firstRenderedChoice ? 'checkbox' : false ) } }
          >
            { childArray }
          </Reveal>
        </Stack>
      );
    }

    default:
      return children;
  }
}
