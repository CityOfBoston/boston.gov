class PublicSafety extends React.Component {

  render() {
    // Content for cards
    const contentPolice = [
      {
        heading: "Station",
        content: <div>
                    <div>{this.props.police_station_name}</div>
                    <div>{this.props.police_station_adress}</div>
                    <div>{(this.props.police_station_neighborhood !== null ? this.props.police_station_neighborhood + ", MA"  : "" )}</div>
                    <div>{this.props.police_station_zip}</div>
                  </div>
      },
      {
        heading: "District",
        content: this.props.police_district
      },
      {
        content: <div>
                    Learn more about the City's <a href={"/departments/police"} className="mnl-link">Police Department</a>.
                    <spacefill></spacefill>
                 </div>
      }
    ]
    const contentFire = [
      {
        heading: "Station",
        content: <div>
                    <div>{this.props.fire_station_name}</div>
                    <div>{this.props.fire_station_address}</div>
                    <div>{(this.props.fire_station_neighborhood !== null ? this.props.fire_station_neighborhood + ", MA"  : "" )}</div>
                  </div>
      },
      {
        content: <div>
                    Learn more about the City's <a href={"/departments/fire-operations"} className="mnl-link">Fire Department</a>.
                    <spacefill></spacefill>
                 </div>
      }
    ];
    const configCards = configProps.sections.public_safety.cards;
    const secDesc = "Find the nearest police and fire stations to your address.";
    const cardsPublicSafety = (
      <div>
        <div className="sh">
          <h2 className="sh-title">Public Safety</h2>
        </div>
        <div className="supporting-text">
          <p>Find the nearest police and fire stations to your address.</p>
        </div>
        <div className="g">
          {/* Police Station */}
          {(configCards.police_station.display) ? (
            <MnlCard
              title={"A Police Station Near You"}
              image_header={
                configProps.globals.pathImage+"police.svg"
              }
              content_array={contentPolice}
            />
          ) : null}

          {/* Fire Station */}
          {(configCards.fire_station.display) ? (
            <MnlCard
              title={"A Fire Station Near You"}
              image_header={
                configProps.globals.pathImage+"fire_dept.svg"
              }
              content_array={contentFire}
            />
          ) : null}

        </div>
        <button className="t--upper t--sans"
          onClick={() => {
            this.props.displaySection(null);
          }}
        >
          Back to results
        </button>
      </div>
    );

    let displayPublicSafety;
    if (this.props.section == "public_safety") {
      {(!configProps.frame_google() ? history.pushState(null, null, configProps.globals.path+'?p3') : null)};
      displayPublicSafety = cardsPublicSafety;
    } else if (this.props.section == null) {
      displayPublicSafety = (
        <a
          className="cd g--4 g--4--sl m-t500 cdp-l mnl-section"
          title={"Public Safety"}
          style={{ textAlign: "left" }}
          onClick={() => {
            this.props.displaySection("public_safety");
          }}
          onKeyUp={() => {
            this.props.displaySection("public_safety",event);
          }}
        >
          <MnlSection
            title={"Public Safety"}
            image_header={
              configProps.globals.pathImage+"first_aid.svg"
            }
            desc={secDesc}
          />
        </a>
      );
    } else {
      displayPublicSafety = null;
    }
    return displayPublicSafety;
  }
}
