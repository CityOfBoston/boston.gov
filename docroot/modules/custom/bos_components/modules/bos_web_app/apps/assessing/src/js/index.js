// Import not needed because React, ReactDOM, and local/global compontents are loaded by *.libraries.yml

class MyWebApp extends React.Component {
  constructor(props) {
    super(props);
  }

  render() {
      return(
      	<div></div>
      ) 
  }
}

ReactDOM.render(<MyWebApp />,
  document.getElementById("web-app")
);
