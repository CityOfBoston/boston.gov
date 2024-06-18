class WinterResources extends React.Component {
  render() {
    // Content for cards
    const contentSnowEmergencyParking = [
      {
        heading: "Parking Lot Name",
        content: this.props.snow_parking_lots_name
      },
      {
        heading: "Parking Lot Address",
        content: this.props.snow_parking_lots_address
      },
      {
        heading: "Parking Lot Fee",
        content: this.props.snow_parking_lots_fee
      }
    ]
    const parking_comments = this.props.snow_parking_lots_comments;
    if(this.props.snow_parking_lots_comments !== null && parking_comments.trim() !== ""){
      contentSnowEmergencyParking.push({
        heading: "Parking Lot Comments",
        content: this.props.snow_parking_lots_comments
      });
    }
    const contentSnowRoutes = [
      {
        heading: "Closest Route",
        content: this.props.snow_routes
      },
      {
        heading: "NOTE:",
        content: (
          <div>
            <div>During a declared snow emergency, we will ticket and tow your car if you park on a snow route.</div>
          </div>
        )
      }
    ];
    const contentSnowParkingRestrictions = [
      {
        content: (
            <div>There may be other snow emergency arteries or emergency parking areas near you. <a href={"/departments/311/snow-emergency-parking"} className="mnl-link">View all available information.</a></div>
        )
      }
    ];
    const configCards = configProps.sections.winter.cards;
    const secDesc = "Find you where you can / can’t park and how to prepare for snow emergencies.";
    const cardsWinter = (
      <div>
        <div className="sh">
          <h2 className="sh-title">Winter Resources</h2>
        </div>
        <div className="supporting-text">
          <p>We will ticket and tow your car if you park on a posted snow emergency artery during a declared snow emergency. If you can't find a spot, some lots and garages offer discounted parking to vehicles with Boston resident parking stickers. <a href={"/winter-boston"} className="mnl-link">Learn more about winter resources in Boston.</a></p>
        </div>
        <div className="g">
          {/* Emergency Parking */}
          {(configCards.snow_emergency.display) ? (
            <MnlCard
              title={"Snow Emergency Parking Near You"}
              image_header={
                configProps.globals.pathImage+"parking.svg"
              }
              content_array={contentSnowEmergencyParking}
            />
          ) : null}

          {/* Snow Routes */}
          {(configCards.snow_route.display) ? (
            <MnlCard
              title={"A Snow Emergency Artery Near You"}
              image_header={
                configProps.globals.pathImage+"plan.svg"
              }
              content_array={contentSnowRoutes}
            />
          ) : null}

          {/* Snow Routes */}
          {(configCards.snow_parking_restrictions.display) ? (
            <MnlCard
              title={"Additional snow parking resources"}
              image_header={
                configProps.globals.pathImage+"snow_parking.svg"
              }
              content_array={contentSnowParkingRestrictions}
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

    let displayWinter;
    if (this.props.section == "winter") {
      {(!configProps.frame_google() ? history.pushState(null, null, configProps.globals.path+'?p3') : null)};
      displayWinter = cardsWinter;
    } else if (this.props.section == null) {
      displayWinter = (
        <a
          className="cd g--4 g--4--sl m-t500 cdp-l mnl-section"
          title={"Winter Resources"}
          style={{ textAlign: "left" }}
          onClick={() => {
            this.props.displaySection("winter");
          }}
          onKeyUp={() => {
            this.props.displaySection("winter",event);
          }}
        >
          <MnlSection
            title={"Winter Resources"}
            image_header={
              configProps.globals.pathImage+"snow_1.svg"
            }
            desc={secDesc}
          />
        </a>
      );
    } else {
      displayWinter = null;
    }
    return displayWinter;
  }
}
