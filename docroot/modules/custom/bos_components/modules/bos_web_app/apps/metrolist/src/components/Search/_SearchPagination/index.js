import React from 'react';
import PropTypes from 'prop-types';

import './SearchPagination.scss';

function SearchPagination( props ) {
  const { pages, currentPage } = props;

  return (
    <ul className="pg">
      {
        currentPage <= 1 ? (
          <li className="pg-li pg-disabled pg-first">
            <span className="pg-li-i">« First</span>
          </li>
        ) : (
          <li className="pg-li pg-previous pg-first" >
            <a className="pg-li-i pg-li-i--link"  href="?page=1">« First</a>
          </li>
        )
      }
      {
        currentPage <= 1 ? (<div />) : (
        <li className="pg-li">
          <a className="pg-li-i pg-li-i--link" href={"?page=" + (currentPage - 1)} title="Go to previous page" rel="prev">
            <span className="visually-hidden">Previous page</span>
            <span aria-hidden="true">‹‹</span>
          </a>
      </li>)
      }
      {
        pages.map( ( pageNumber, index ) => {
          const isCurrentPage = ( currentPage === pageNumber );

          return (
            <li className="pg-li" key={ index }>
              <a
                className={"pg-li-i pg-li-i--link" + (isCurrentPage ? " pg-li-i--a" : "" )}
                href={"?page=" + (index+1)}
              >{ pageNumber }</a>
            </li>
          );
        } )
      }
      {
        currentPage >= pages.length ? (<div />) : (
        <li className="pg-li">
          <a className="pg-li-i pg-li-i--link" href={"?page=" + (currentPage + 1)} title="Go to next page" rel="next">
            <span className="visually-hidden">Next page</span>
            <span aria-hidden="true">››</span>
          </a>
        </li>)
      }
      {
        currentPage >= pages.length ? (
          <li className="pg-li pg-disabled pg-last">
            <span className="pg-li-i">Last »</span>
          </li>
        ) : (
          <li className="pg-li pg-next pg-last">
            <a className="pg-li-i pg-li-i--link" href={"?page=" + pages.length}>Last »</a>
          </li>
        )
      }
    </ul>
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
