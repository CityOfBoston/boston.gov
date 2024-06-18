class CityServices extends React.Component {

   render() {
    // Content for Trash and Recycling Card
    let contentRecollect = [];
    let recollectData = this.props.recollect_events;
    let found = null;
    // console.log(recollectData);
    if(recollectData !== null) {
      Object.keys(recollectData).map(function(key,index){
        if(found !== true && recollectData[key].flags){
          Object.keys(recollectData[key].flags).map(function(key_flag,index_flag){
            if (recollectData[key].flags[key_flag].name == "Trash" || recollectData[key].flags[key_flag].name == "Recycling" ) {
              const dateProp = recollectData[key].day;
              const dateArray = dateProp.split('-');
              const dateFormat = new Date(dateArray[1] + '/' + dateArray[2] + '/' + dateArray[0]);
              const dateDays = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
              const dateMonths = ["January","February","March","April","May","June","July","August","September","October","November","December"];
              contentRecollect.push({
                heading: "Your next "+ recollectData[key].flags[key_flag].name +" day is:",
                content: dateDays[dateFormat.getDay()] +', '+ dateMonths[dateFormat.getMonth()] + ' ' + dateFormat.getDate()
              })
              found  = true;
            }
          })
        }
      })
      contentRecollect.push({
        heading: "NOTE:",
        content: <div>The trash and recycling schedule might change during holidays and weather events.</div>
      });
    } else {
      contentRecollect.push({
        content: (
          <div>
            <div>We're having trouble finding Trash Collection information for that address. This may be because your trash is picked up by a commercial vendor.</div>
            <div className="no-heading">If that’s not right or you have additional questions, please reach out to our <a href={"/departments/public-works"} className="mnl-link">Public Works Department</a> at publicworks@boston.gov or 617-635-4900.</div>
          </div>
          )
      });
    }

    contentRecollect.push({
      content:<div>Learn more about <a href={"/trash-and-recycling"} className="mnl-link">trash and recycling</a>.</div>
      });

    // Content for Street Sweeping
    const contentStreetSweeping = [
      {
        content: (
           <div>
              <div>The City cleans streets throughout the year.</div>
              <div className="no-heading">Learn about our street sweeping <a href={"/departments/public-works/street-sweeping-city"} className="mnl-link">program</a> or find the street sweeping <a href={"https://www.cityofboston.gov/publicworks/sweeping/"} target="_blank" rel="noreferrer" className="mnl-link">schedule</a> for your street.</div>
          </div>
        )
      }
    ];
    const configCards = configProps.sections.city_services.cards;
    const secDesc = "Trash / recycling pick up and street sweeping.";
    const cardsCityServices = (
      <div>
        <div className="sh">
          <h2 className="sh-title">City Services</h2>
        </div>
        <div className="supporting-text">
          <p>{secDesc}</p>
        </div>
        <div className="g">
          {/* Trash and recycling info */}
          {(configCards.trash_and_recycling.display) ? (
            <MnlCard
              title={"Trash and Recycling"}
              image_header={
                configProps.globals.pathImage+"trash_truck.svg"
              }
              content_array={contentRecollect}
            />
          ) : null}

          {/* Street Sweeping */}
          {(configCards.street_sweeping.display) ? (
            <MnlCard
              title={"Street Sweeping"}
              image_header={
                configProps.globals.pathImage+"street_sweeper.svg"
              }
              content_array={contentStreetSweeping}
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
    let displayCityServices;
    if (this.props.section == "city-services" ) {
      {(!configProps.frame_google() ? history.pushState(null, null, configProps.globals.path+'?p3') : null)};
      displayCityServices = cardsCityServices;
    } else if (this.props.section == null) {
      displayCityServices = (
        <a
          className="cd g--4 g--4--sl m-t500 cdp-l mnl-section"
          title={"City Services"}
          style={{ textAlign: "left" }}
          onClick={() => {
            this.props.displaySection("city-services");
          }}
          onKeyUp={() => {
            this.props.displaySection("city-services",event);
          }}
        >
          <MnlSection
            title={"City Services"}
            image_header={
              configProps.globals.pathImage+"street_sweeper.svg"
            }
            desc={secDesc}
          />
        </a>
      );
    } else {
      displayCityServices = null;
    }
    return displayCityServices;
  }
}
