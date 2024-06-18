class SummerResources extends React.Component {
  render() {
    // Content for card
    const contentPools = [
      {
        heading: "Pool Location",
        content: <div>
                    <div>{this.props.bcyf_pool_center_name}</div>
                    <div>{this.props.bcyf_pool_center_address}</div>
                  </div>
      },
      {
        content: (
          <div>
            BCYF Clougherty and Mirabella outdoor pools are open at limited capacity by reservation only to City of Boston residents. All other BCYF indoor pools will remain open only for participating youth for in-person summer programming at this time. We have new protocols and procedures this year that you can read more about on the <a href={"/departments/boston-centers-youth-families"} className="mnl-link">BCYF page</a>.
            <spacefill></spacefill>
          </div>
        )
      }
    ];
    const contentTotSprays = [
      {
        heading: "Splash Pad Location",
        content: <div>
                    <div>{this.props.tot_name}</div>
                    <div>{this.props.tot_address}</div>
                  </div>
      },
      {
        content: (
          <div>
            Learn more about the <a href={"/summer-boston"} className="mnl-link">City in the summer</a>.
            <spacefill></spacefill>
          </div>
        )
      }
    ];
    const configCards = configProps.sections.summer.cards;
    const secDesc = "Pools and splash pads near you.";
    const cardsSummer = (
      <div>
        <div className="sh">
          <h2 className="sh-title">Summer Resources</h2>
        </div>
        <div className="supporting-text">
          <p>{secDesc}</p>
        </div>
        <div className="g">
          {/* Pool info */}
          {(configCards.pool.display) ? (
            <MnlCard
              title={"A Pool Near You"}
              image_header={
                configProps.globals.pathImage+"pool.svg"
              }
              content_array={contentPools}
            />
          ) : null}

          {/* Splash Pads */}
          {(configCards.splash_pad.display) ? (
            <MnlCard
              title={"A Splash Pad Near You"}
              image_header={
                configProps.globals.pathImage+"tot_spray.svg"
              }
              content_array={contentTotSprays}
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
    let displaySummer;
    if (this.props.section == "summer") {
      {(!configProps.frame_google() ? history.pushState(null, null, configProps.globals.path+'?p3') : null)};
      displaySummer = cardsSummer;
    } else if (this.props.section == null) {
      displaySummer = (
        <a
          className="cd g--4 g--4--sl m-t500 cdp-l mnl-section"
          title={"Summer Resources"}
          style={{ textAlign: "left" }}
          onClick={() => {
            this.props.displaySection("summer");
          }}
          onKeyUp={() => {
            this.props.displaySection("summer",event);
          }}
        >
          <MnlSection
            title={"Summer Resources"}
            image_header={
              configProps.globals.pathImage+"sun.svg"
            }
            desc={secDesc}
          />
        </a>
      );
    } else {
      displaySummer = null;
    }
    return displaySummer;
  }
}
