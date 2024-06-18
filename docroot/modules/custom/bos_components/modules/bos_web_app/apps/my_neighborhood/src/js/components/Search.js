

class Search extends React.Component {
  render() {
      return (
        <div className="sf">
          <div className="sf-i">
          <form id="mnl-search-form" onSubmit={this.props.handleKeywordSubmit}>
            <input
                type="text"
                id="mnl-search-input"
                value={this.props.currentKeywords}
                style={this.props.styleInline}
                aria-label={this.props.placeholder}
                placeholder={this.props.placeholder}
                className={this.props.searchClass + ' search'}
                onChange={this.props.handleKeywordChange}
            />

            <label
              htmlFor="mnl-search-input"
              className="sf-i-l">
              Search
            </label>

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
            <span style ={this.props.styleInline} className={this.props.searchClass}>{this.props.currentKeywords}</span>
          </div>
        </div>
      )
  }
}


