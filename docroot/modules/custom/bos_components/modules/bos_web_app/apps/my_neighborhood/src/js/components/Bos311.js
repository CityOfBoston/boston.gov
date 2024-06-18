class Bos311 extends React.Component {

  render() {
    // Content for card
    let display311;
    if (this.props.section == null) {
      const secDesc = "Report non-emergency issues, like potholes and graffiti.";
      display311 = (
        <a
        className="cd g--4 g--4--sl m-t500 cdp-l bg--y mnl-section mnl-link"
        title={"Boston 311"}
        style={{ textAlign: "left" }}
        href={"/departments/bos311"}
        target="_blank"
        >
          <MnlSection
            title={"Boston 311"}
            image_header={
              configProps.globals.pathImage + "bos_311.svg"
            }
            desc={secDesc}
          />
        </a>
      );
    } else {
      display311 = null;
    }
    return display311;
  }
}
