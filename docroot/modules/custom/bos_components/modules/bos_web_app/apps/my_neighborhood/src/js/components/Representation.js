class Representation extends React.Component {
  render() {
    // Content for cards
    const configMayor = configProps.sections.representation.cards.mayor;
    let contentMayor;
    contentMayor = [
        {
          heading: configMayor.name,
          content: "Mayor"
        },
        { content:
            <div>
              Learn more about <a href={configMayor.url} className="mnl-link">Boston's Mayor</a>.
            </div>
        }
    ];
    let contentRepArray;
    if (this.props.councilor !== null) {
      contentRepArray = [
          {
            heading: this.props.councilor,
            content: "City Councilor, District " + this.props.district
          },
          {
            content:
              <div>
                To learn more about Boston's City Council and view all members, visit the <a href={"/departments/city-council#city-council-members"} className="mnl-link">City Council page</a>.
              </div>
          }
      ];
    } else {
      contentRepArray = [
        {
          content: <div>We're having trouble finding City Council information for that address. Please let us know at feedback@boston.gov and check our <a href={"/departments/city-council"} title={"City Councilor"} className="mnl-link">City Council page</a> page for more information.</div>
        }
      ];
    }
    let contentLiasonArray;
    if (this.props.liason_name !== null) {
      contentLiasonArray = [
        {
          heading: this.props.liason_name,
          content: "Contact: " + this.props.liason_neighborhood
        },
        {
          content: (
            <div>
              Learn more about <a href={"/departments/neighborhood-services"} className="mnl-link">Neighborhood Services</a>.
            </div>
          )
        }
      ];
    } else {
      contentLiasonArray = [
        {
          content: <div>We're having trouble finding Neighborhood Contact information for that address. Please let us know at feedback@boston.gov and check our <a href={"/departments/neighborhood-services"} title={"Neighborhood Services"} className="mnl-link">Neighborhood Services page</a> page for more information.</div>
        }
      ];
    }
    let contentPollingArray;
    let contentPolingArrayRedistrict;
    if (this.props.ward == null || this.props.precinct == null) {
      contentPollingArray = [
        {
          content: <div>We're having trouble finding your voting information for that address. Please let us know at feedback@boston.gov and check our <a href={"/voting-boston"} title={"Voting in Boston"} className="mnl-link">Voting in Boston</a> page for more information.</div>
        }
      ];
    } else {
      contentPollingArray = [
        /*{
          heading: this.props.voting_location,
          content: this.props.voting_address
        },*/
        {
          heading: "Ward",
          content: this.props.ward
        },
        {
          heading: "Precinct",
          content: this.props.precinct
        },
        {
          content: <div><a href={"https://www.sec.state.ma.us/VoterRegistrationSearch/MyVoterRegStatus.aspx"} target="_blank" rel="noreferrer" className="mnl-link">Check the state's website</a> to find out if you are registered to vote, and where your polling location is.</div>
        },

        {
          content: <ul class="ul"><li><a href={"https://boston.maps.arcgis.com/apps/webappviewer/index.html?id=72a95777f7e842eaae3671c0d67acce0&find=" + this.props.sam_id} target="_blank" rel="noreferrer" className="mnl-link">Explore the City's wards and precincts</a></li></ul>
        }
      ];
      // DU DIG-2904 & DIG-3820
      contentPolingArrayRedistrict = [
        {
          content: <div>Due to redistricting, your District City Councilor may change as of January 1, 2024. To find out which race you are voting for in the 2023 Municipal Election, <a href={"/departments/elections/find-out-who-you-are-voting-2023-district-city-council-race"}>please refer to our informational website.</a></div>
        }
      ]

    }
    let contentEarlyVotingArray;
    if (this.props.early_voting_dates !== null) {
        contentEarlyVotingArray = [
        {
          heading: "Location",
          content: <div>
                      <div>{this.props.early_voting_location}</div>
                      <div>{this.props.early_voting_address}</div>
                    </div>
        },
        {
          heading: "Date/Time",
          content: <div>
                      <div>Starts {this.props.early_voting_dates}</div>
                      <div>{this.props.early_voting_times}</div>
                  </div>
        },
        {
          heading: "Neighborhood",
          content: this.props.early_voting_neighborhood
        }]
        if (this.props.early_voting_notes !== null) {
          contentEarlyVotingArray.push({
            heading: "Notes",
            content: this.props.early_voting_notes
          })
        }
    } else {
        contentEarlyVotingArray = [
        {
          heading: "",
          content: <div>No early voting data available.</div>
        }]
    };
    contentEarlyVotingArray.push({
          content: (
            <div>
              Learn more about <a href={"/departments/elections/vote-early-boston"} className="mnl-link">early voting in Boston</a>.
            </div>
          )
    });
    // Content for State and Feferal
    const contentStateFederalReps = [
      {
        content: (
            <div >For more information on your State and Federal representatives, visit the <a href={"https://malegislature.gov/search/findmylegislator"} target="_blank" rel="noreferrer" className="mnl-link">Find My Legislator tool</a> on <a href={"https://www.mass.gov"} target="_blank" rel="noreferrer" className="mnl-link">Mass.gov</a>.
            </div>
        )
      }
    ];
    // Content for Councilors at Large
    const contentCouncilorsatLargeReps = [
      {
        // DU DIG-2904 & DIG-3820
        content: (
          <div class="councilor-at-large">
            <div class="intro">The four at-large councilors that represent the entire city:</div>
            <div>
              <a href={'/departments/city-council/ruthzee-louijeune'}
                 className={'mnl-link link_underline'}>Ruthzee Louijeune</a>
              <a href={'/departments/city-council/julia-mejia'}
                 className={'mnl-link link_underline'}>Julia Mejia</a>
              <a href={'/departments/city-council/erin-murphy'}
                 className={'mnl-link link_underline'}>Erin Murphy</a>
              <a href={'/departments/city-council/henry-santana'}
                 className={'mnl-link link_underline'}>Henry Santana</a>
            </div>
          </div>
        )
      }
    ];
    const configCards = configProps.sections.representation.cards;
    const secDesc =
      "Your representation and voting information.";
    const cardsRepresentation = (
      <div>
        <div className="sh">
          <h2 className="sh-title">Representation</h2>
        </div>
        <div className="supporting-text">
          <p>{secDesc}</p>
        </div>
        <div className="g">
          {/* Polling Info */}
          {(configCards.polling_information.display) ? (
            <MnlCard
              // DU DIG-2904 & DIG-3820
              title={configCards.polling_information.redistricting ? "City Council and Voting Information": "Your Polling Information"}
              image_header={
                configProps.globals.pathImage+"voting_ballot.svg"
              }
              content_array={configCards.polling_information.redistricting ? contentPolingArrayRedistrict  :contentPollingArray}
            />
          ) : null}

        {/* City Mayor */}
          {(configCards.mayor.display) ? (
            <MnlCard
              title={"Your Mayor"}
              image={
                configMayor.image
              }
              image_href={
                configMayor.url
              }
              content_array={contentMayor}
            />
          ) : null}

          {/* Neighborhood Contact */}
          {(configCards.neighborhood_contact.display) ? (
            <MnlCard
              title={"Your Neighborhood Contact"}
              image={
                this.props.liason_image !== null && this.props.liason_name
                  ? this.props.liason_image
                  : "https://patterns.boston.gov/images/global/icons/experiential/meet-archaeologist.svg"
              }
              image_href={
                this.props.liason_name !== null
                  ? this.props.liason_webpage
                  : "/departments/neighborhood-services"
              }
              content_array={contentLiasonArray}
            />
          ) : null}

          {/* City Councilor */}
          {(configCards.city_councilor.display) ? (
            <MnlCard
              title={"Your City Councilor"}
              image={
                this.props.councilor_image !== null && this.props.councilor
                  ? this.props.councilor_image
                  : "https://patterns.boston.gov/images/global/icons/experiential/meet-archaeologist.svg"
              }
              image_href={
                this.props.councilor !== null
                  ? this.props.councilor_webpage
                  : "departments/city-council"
              }
              content_array={contentRepArray}
            />
          ) : null}

          {/* Early Voting Info */}
          {(configCards.early_voting.display) ? (
            <MnlCard
              title={"An Early Voting Location Near You"}
              image_header={
                configProps.globals.pathImage+"voting_ballot.svg"
              }
              content_array={contentEarlyVotingArray}
            />
          ): null}

          {/* City Councillors at Large */}
          {(configCards.city_councilor_at_large.display) ? (
            <MnlCard
              title={"Your At-Large City Councilors"}
              image_header={
                "https://patterns.boston.gov/images/global/icons/experiential/podium.svg"
              }
              content_array={contentCouncilorsatLargeReps}
            />
          ): null}

          {/* State and Federal Reps */}
          {(configCards.state_federal_reps.display) ? (
            <MnlCard
              title={"State and Federal Representatives"}
              image_header={
                "https://patterns.boston.gov/images/global/icons/experiential/meet-archaeologist.svg"
              }
              content_array={contentStateFederalReps}
            />
          ): null}

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
    let displayReps;
    if (this.props.section == "reps") {
      {(!configProps.frame_google() ? history.pushState(null, null, configProps.globals.path+'?p3') : null)};
      displayReps = cardsRepresentation;
    } else if (this.props.section == null) {
      displayReps = (
        <a
          className="cd g--4 g--4--sl m-t500 cdp-l mnl-section"
          title={"Representation"}
          style={{ textAlign: "left" }}
          onClick={() => {
            this.props.displaySection("reps");
          }}
          onKeyUp={() => {
            this.props.displaySection("reps",event);
          }}
        >
          <MnlSection
            title={"Representation"}
            image_header={
              configProps.globals.pathImage+"conversation.svg"
            }
            desc={secDesc}
          />
        </a>
      );
    } else {
      displayReps = null;
    }
    return displayReps;
  }
}
