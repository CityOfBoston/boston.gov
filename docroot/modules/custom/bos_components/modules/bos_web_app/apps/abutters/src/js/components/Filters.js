import React from "react";
import { Card, Form, FormGroup, Input, Label, Button } from 'reactstrap';
import PropTypes from 'prop-types';
import { CSVLink } from 'react-csv';

class Filters extends React.Component {
//export default function Filters(props) {

  constructor(props) {
    super(props);
  }

  render() {
  // We set up an empty array to house the data we'll export to csv.
  const csvData = [];
  // TODO: figure out what fields are actually needed here.
  if (this.props.bufferParcels.length > 1) {
    this.props.bufferParcels.forEach(parcel => csvData.push(parcel.properties));
  }

  return (
    <div>
      <Card className="border-0 pt-4 ml-2 m-1 pt-3">
        <p className="mb-2" style={{ fontSize: '16px' }}>
          Search for an address or enter a parcel ID below.
        </p>
      </Card>
      <Card className="border-0 ml-1 mr-2">
        <Label htmlFor="geocoder" className="text-uppercase m-1">
          <h6>Address Search</h6>
        </Label>
        <div id="geocoder" style={{ width: '100%' }} className="m-1" />
        <FormGroup className="m-1 pt-3">
          <Label htmlFor="parcelIdSearch" className="text-uppercase">
            <h6>Parcel Search</h6>
          </Label>
          <div id="parcelIDSearchWrapper" className="input-group">
            <Input
              id="parcelIdSearch"
              type="text"
              onChange={this.props.handleParcelIDSearch}
              name="parcelID"
              value={this.props.searchedParcelID}
            ></Input>
            <Button
              id="parcelIDSearchButton"
              onClick={this.props.searchForParcelIDButton}
              className="ml-1"
            >
              Search
            </Button>
          </div>
        </FormGroup>
        <FormGroup className="m-1 pt-3">
          <Label htmlFor="selectedParcelID" className="text-uppercase">
            <h6>Selected Parcel</h6>
          </Label>
          <p className="mb-2">
            {this.props.selectedParcel == null
              ? 'No parcel found'
              : `${this.props.selectedParcelPID} - ${this.props.selectedParcel.properties.FULL_ADDRE}`}
          </p>
        </FormGroup>
      </Card>
      <Card className="border-0 pt-0 ml-2 m-1 pt-3">
        <p className="mb-2" style={{ fontSize: '16px' }}>
          Enter a buffer distance and a the mailing list csv will appear below.
        </p>
      </Card>
      <Card className="border-0 ml-1 mr-2 pb-3">
        <Form>
          <FormGroup className="m-1">
            <Label htmlFor="bufferDistance" className="text-uppercase">
              <h6>Buffer Distance (feet)</h6>
            </Label>
            <Input
              id="bufferDistance"
              type="number"
              min={0}
              max={100000}
              onChange={this.props.handleBufferChange}
              name="bufferDistance"
              value={this.props.bufferDistance}
              className="mb-2"
            />
            {/* {this.props.selectedParcelPID} */}
            <Button onClick={this.props.updateParcelBufferButton}>
              Buffer Parcel
            </Button>
            {this.props.bufferParcels == null ? (
              <p>Please select a parcel before buffering.</p>
            ) : null}
          </FormGroup>
          <FormGroup className="m-1 mt-3">
            <Label
              htmlFor="bufferDistance"
              className="font-weight-bold text-uppercase"
            >
              {/* We only show the csv download link if they array has info in it. */}
              {csvData.length > 0 ? (
                <CSVLink data={csvData} filename={'mailingList.csv'}>
                  Download Mailing List CSV
                </CSVLink>
              ) : null}
            </Label>
          </FormGroup>
        </Form>
      </Card>
    </div>
    );
  }
}

export default Filters;

Filters.propTypes = {
  selectedParcelPID: PropTypes.string,
  handleBufferChange: PropTypes.func,
  bufferDistance: PropTypes.number,
  bufferParcels: PropTypes.array,
  updateParcelBufferButton: PropTypes.func,
  handleParcelIDSearch: PropTypes.func,
  searchedParcelID: PropTypes.string,
  searchForParcelIDButton: PropTypes.func,
  selectedParcel: PropTypes.object,
};
