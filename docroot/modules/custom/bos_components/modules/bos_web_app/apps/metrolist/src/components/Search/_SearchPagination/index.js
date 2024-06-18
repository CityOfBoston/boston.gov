import React from 'react';
import PropTypes from 'prop-types';
import { useLocation } from 'react-router-dom';

import Row from '@components/Row';
import Link from '@components/Link';

import './SearchPagination.scss';

function SearchPagination( props ) {
  const location = useLocation();
  const { className, pages, currentPage } = props;

  return (
    <Row
      data-testid="ml-search__pagination"
      className={ `pg ml-search__pagination${className ? ` ${className}` : ''}` }
      space="panel"
    >{
      pages.map( ( pageNumber, index ) => {
        const isCurrentPage = ( currentPage === pageNumber );

        return (
          <span className="pg-li ml-search__page-link-container" key={ index }>
            <Link
              className={ `pg-li-i pg-li-i--link${isCurrentPage ? ' pg-li-i--a ' : ' '}ml-search__page-link` }
              to={ ( pageNumber > 1 ) ? `${location.pathname}?page=${pageNumber}` : location.pathname }
              aria-label={ `Search Results: Page ${pageNumber}` }
            >{ pageNumber }</Link>
          </span>
        );
      } )
    }</Row>
  );
}

SearchPagination.displayName = 'SearchPagination';

SearchPagination.propTypes = {
  "children": PropTypes.node,
  "className": PropTypes.string,
  "pages": PropTypes.array.isRequired,
  "currentPage": PropTypes.number.isRequired,
};

export default SearchPagination;
