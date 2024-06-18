import React from 'react';
import PropTypes from 'prop-types';

import { slugify } from '@util/strings';
import { handlePseudoButtonKeyDown } from '@util/a11y-seo';
import { getGlobalThis } from '@util/objects';

import Icon from '@components/Icon';
import Row from '@components/Row';

import './FilterGroup.scss';

const globalThis = getGlobalThis();

class FilterGroup extends React.Component {
  constructor( props ) {
    super();
    this.state = {
      "isExpanded": props.isExpanded,
    };
    this.handleExpandCollapse = this.handleExpandCollapse.bind( this );
    this.handleChange = this.handleChange.bind( this );
    this.$filters = React.createRef();
  }

  handleExpandCollapse() {
    this.setState( {
      "isExpanded": !this.state.isExpanded,
    } );
  }

  renderFilters( { children } ) {
    let label;
    const childArray = React.Children.toArray( children );
    const firstChild = childArray.shift();
    let groupId;

    if ( firstChild.type.displayName === 'FilterGroupLabel' ) {
      groupId = slugify( firstChild.props.children );
      label = React.cloneElement(
        firstChild,
        {
          "disclosureTarget": groupId,
          "isExpanded": this.state.isExpanded,
          "handleExpandCollapse": this.handleExpandCollapse,
          "handleDoubleClick": this.handleDoubleClick,
        },
        firstChild.props.children,
      );
    }

    let Wrapper;

    if ( this.props.orientation === 'horizontal' ) {
      Wrapper = Row;
    } else {
      Wrapper = 'div';
    }

    return (
      <>
        { label }
        <Wrapper
          id={ groupId }
          ref={ this.$filters }
          className={ `ml-filter-group__filters${this.state.isExpanded ? ' ml-filter-group__filters--expanded' : ''}` }
        >
          { childArray }
        </Wrapper>
      </>
    );
  }

  handleDoubleClick( event ) {
    // https://stackoverflow.com/a/43321596/214325
    if ( event.detail > 1 ) { // Number of clicks
      event.preventDefault();
    }
  }

  handleChange( event ) {
    event.persist();
    event.metrolist = {
      ...event.metrolist,
      "parentCriterion": this.props.criterion,
    };
  }

  render() {
    return (
      <fieldset
        className={
          `ml-filter-group${
            this.props.className ? ` ${this.props.className}` : ''
          }${
            this.state.isExpanded ? ' ml-filter-group--expanded' : ''
          }`
        }
        onChange={ this.handleChange }
      >
        { /* <Wrapper> */ }
        { this.renderFilters( this.props ) }
        { /* </Wrapper> */ }
      </fieldset>
    );
  }
}
FilterGroup.propTypes = {
  "isExpanded": PropTypes.bool,
  "orientation": PropTypes.oneOf( ['vertical', 'horizontal'] ),
  "children": PropTypes.node,
  "className": PropTypes.string,
  "criterion": PropTypes.string,
};
FilterGroup.defaultProps = {
  "isExpanded": globalThis.matchMedia( '(min-width: 992px)' ).matches,
  "orientation": "vertical",
};

FilterGroup.Label = function FilterGroupLabel( props ) {
  /*
    Note: VoiceOver reads '⌃' as “control” (as in the Ctrl key; incorrect)
    However, '⌄' is “down arrowhead” (correct)
    Replace with show and hide text
    May want to replace with black pointing triangles? ▲ ▼
  */
  const isExpandedIndicator = ( props.isExpanded ? '- hide' : '+ show' );
  // const isExpandedIndicator = '';
  const ariaLabel = `${`${props.children}`.trim()} ${isExpandedIndicator}`;

  return (
    <legend
      className="ml-filter-group__label"
      aria-label={ ariaLabel }
      // role="button"
      aria-expanded={ props.isExpanded.toString() }
      aria-controls={ props.disclosureTarget }
      onClick={ props.handleExpandCollapse }
      onMouseDown={ props.handleDoubleClick }
      onKeyDown={ ( event ) => handlePseudoButtonKeyDown( event, true ) }
      tabIndex="0"
    >
      <span className="ml-filter-group__label-ui-fix">
        <span className="ml-filter-group__label-text">{ props.children }</span>
        <span className="ml-filter-group__icon">{ isExpandedIndicator }</span>
      </span>
    </legend>
  );
};
FilterGroup.Label.displayName = 'FilterGroupLabel';
FilterGroup.Label.propTypes = {
  "handleExpandCollapse": PropTypes.func,
  "handleDoubleClick": PropTypes.func,
  "isExpanded": PropTypes.bool,
  "disclosureTarget": PropTypes.string,
  "children": PropTypes.node,
};

export default FilterGroup;
