class Search extends React.Component {
  render () {
    const SearchInputElem = () => {
      let attributes = {
        type: this.props.type,
        id: this.props.id,
        value: this.props.value,
        placeholder: this.props.placeholder,
        ['aria-label']: this.props.placeholder,
        onChange: this.props.handleKeywordChange,
        className: ' search',
      };
  
      if (this.props.searchClass)
        attributes['className'] = `${this.props.searchClass} search`
  
      if (this.props.style)
        attributes['style'] = this.props.styleInline;

      return <input { ...attributes} />
    }

    return (
      <div className="sf">
        <div className="sf-i">
          <form id="mnl-search-form" onSubmit={this.props.handleKeywordSubmit}>
            {SearchInputElem()}

            <button
              type="button"
              id="mnl-search-button"
              className="sf-i-b"
              onClick={this.props.handleKeywordSubmit}
            >
              Search
            </button>
          </form>
        </div>
        <div className="sf-i resize">
          <span style={this.props.styleInline} className={this.props.searchClass}>{this.props.value}</span>
        </div>
      </div>
    )
  }
}

Search.defaultProps = {
  type: 'text',
  id: 'mnl-search-input',
  className: ' search',
}
