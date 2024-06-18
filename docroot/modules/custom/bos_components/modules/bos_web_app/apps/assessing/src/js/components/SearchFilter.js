class SearchFilters extends React.Component {
  render () {
    const RadioBtnElems = () => {
      return this.props.searchFilters.map((obj, index) => {
        let attributes = {
          id: `radio[${index}]`,
          type: 'radio',
          name: `assessing_searchFilters`,
          className: 'ra-f',
          labelText: obj.label,
          index,
          onChange: this.props.onChange,
        };

        if (this.props.searchByFilter === index)
          attributes['checked'] = 'checked';
        return <RadioBtn {...attributes} />;
      });
    }

    return(
      <div className="search-filters">
        <label className="filters-label">Search By:</label>
        {RadioBtnElems()}
      </div>
    );
  }
}