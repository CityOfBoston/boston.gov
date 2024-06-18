// Import not needed because React, ReactDOM, and local/global compontents are loaded by *.libraries.yml
class MNL extends React.Component {
  constructor (props) {
    super(props);
    
    this.state = {
      error: null,
      isLoading: false,
      itemsLookup: [],
      currPage: 1,
      pageMaxCount: 50,
      itemsDisplay: null,
      currentKeywords: '',
      submittedAddress: '',
      submittedKeywords: false,
      searchByFilter: 0,
      romanNumConversion: {
        '1st': 'First',
        '2nd': 'Second',
        '3rd': 'Third',
        '4th': 'Fourth',
        '5th': 'Fifth',
        '6th': 'Sixth',
        '7th': 'Seventh',
        '8th': 'Eighth',
        '9th': 'Ninth'
      },
      searchFilters: [
        {
          value: 'address',
          label: 'Address',
          placeholder: "Search by address",
          examples: [
            'Street name, street suffix ex. A Street',
            'Street #, street name, suffix abbreviation ex. 1 City Hall Sq',
            'Street #, street name, suffix ex. 50 Blue Hill Avenue',
            'Street #, street name, suffix abbreviation, Apt/Unit number ex:',
            [
              'A street',
              'A street Apt 1',
              'A street Apt 1-A',
              'Third st',
              'Third st Apt 1',
              '350 Blue Hill Avenue Apt #3',
              '350 Blue Hill Avenue Unit 3',
              '350 Blue Hill Avenue #3',
            ]
          ],
          instructions: "Please 'search by address' in the following formats:",
          // instructions: "Enter your address by street #, street name, suffix (Street, Ave, etc) ex. 1 City Hall Sq",
        },
        // NOTE: SEARCH BY OWNER has been requested to be disabled
        // {
        //   value: 'owner',
        //   label: "Property Owner",
        //   instructions: "Enter the property owner's name (first, last) ex. First and Last Name",
        //   placeholder: "Search by First and Last Name",
        // },
        {
          value: 'id',
          label: 'Parcel ID',
          instructions: "Enter the Property ID number ex. 0504203000",
          placeholder: "Search by Property ID",
        },
      ],
      queryStr: {
        base: `https://data.boston.gov/api/3/action/datastore_search_sql`,
        sql: {
          pre: `?sql=SELECT%20*%20from%20%228de4e3a0-c1d2-47cb-8202-98b9cbe3bd04%22%20WHERE%20%22`,
          filters: [
            {
              pre: `ST_NUM%22%20LIKE%20%27100%27%20AND%20%22ST_NAME%22%20LIKE%20%27HOWARD%27%20AND%20%22ST_NAME_SUF%22%20LIKE%20%27AV%27`,
              post: `%20ORDER%20BY%20%22ST_NUM%22%20,%20%22ST_NAME%22%20DESC%20LIMIT%20100`,
              parseObj: {
                bridge: `%20AND%20`,
                st_num: `ST_NUM%22%20LIKE%20%27__value__%27`,
                st_name: `%22ST_NAME%22%20LIKE%20%27__value__%27`,
                st_name_suffix: `%22ST_NAME_SUF%22%20LIKE%20%27__value__%27`,
              },
            },
            // NOTE: SEARCH BY OWNER has been requested to be disabled
            // {
            //   pre: `OWNER%22%20LIKE%20%27`,
            //   post: `%%27%20ORDER%20BY%20%22OWNER%22%20DESC%20LIMIT%20100`
            // },
            {
              pre: `PID%22%20LIKE%20%27`,
              post: `%27%20ORDER%20BY%20%22PID%22%20DESC%20LIMIT%20100`,
            },
          ],
        },
        sql2: {
          base: '/assessing-online/lookup?',
          filters: [
            {
              street_number: 'street_number=__value__&',
              street_name_only: 'street_name_only=__value__&',
              street_suffix: 'street_suffix=__value__&',
              apt_unit: 'apt_unit=__value__&',
            },
            // {}, // Search by OWNER
            ['parcel_id=__value__']
          ],
          sort: ''
        }
      },
      st_suffix: [
        { abbr: 'AL', label: 'Alley' },
        { abbr: 'AV', label: 'Avenue' },
        { abbr: 'AV', label: 'Ave' },
        { abbr: 'AVE', label: 'Avenue' },
        { abbr: 'BL', label: 'BLVD' },
        { abbr: 'CI', label: 'Circle' },
        { abbr: 'CT', label: 'Court' },
        { abbr: 'DM', label: 'Dam' },
        { abbr: 'DR', label: 'Drive' },
        { abbr: 'HW', label: 'Highway' },
        { abbr: 'PK', label: 'Park' },
        { abbr: 'PW', label: 'Parkway' },
        { abbr: 'PL', label: 'Place' },
        { abbr: 'PZ', label: 'Plaza' },
        { abbr: 'RD', label: 'Road' },
        { abbr: 'RO', label: 'Row' },
        { abbr: 'SQ', label: 'Square' },
        { abbr: 'ST', label: 'Street' },
        { abbr: 'TE', label: 'Terrace' },
        { abbr: 'WY', label: 'Way' },
      ],
      aptUnitLabels: ["apt", "apt.", "unit", "unit.", "#"],
      validationMgs: "",
      postResMessage: ""
    };
  }

  componentDidMount () {
    let inputHeight = jQuery("#web-app input").height();
    let inputWidth = jQuery("#web-app input").width() - 75;
    jQuery(".resize").css('height', inputHeight + 'px');
    jQuery(".resize").css('width', inputWidth + 'px');
    jQuery("#web-app input").css('height', inputHeight + 'px');

    let that = this;
    window.addEventListener('popstate', function () {
      that.setDefaults();
    }, false);
  }

  setDefaults = () => {
    { (!configProps.frame_google() ? history.pushState(null, null, configProps.globals.path) : null) }
  }

  scaleInputText = () => {
    jQuery(".resize").textfill({
      minFontPixels: 20,
      maxFontPixels: 75,
      success: function () {
        let fontReSize = jQuery(".resize span").css('fontSize');
        jQuery("#web-app input").css('font-size', fontReSize);
      }
    })
  }

  handleKeywordChange = event => {
    event.preventDefault();
    this.setState({
      currentKeywords: event.target.value,
      submittedKeywords: false,
      currPage: 1
    });

    if (event.keyCode === 13) {
      this.setState({
        isLoading: true,
        submittedKeywords: true,
        submittedAddress: ''
      });
    }
  }

  handleKeywordSubmit = event => {
    event.preventDefault();
    this.setState({
      isLoading: true,
      submittedKeywords: true,
      submittedAddress: '',
      currPage: 1
    });
    this.lookupAddress();
  };

  handleLoadMoreResults = event => {
    event.preventDefault();
    this.setState({
      currPage: ++this.state.currPage
    });
  };

  handleChangeSearchFilterHandler = ev => {
    this.setState({
      itemsLookup: [],
      searchByFilter: ev.currentTarget.value,
      submittedKeywords: false,
      submittedAddress: '',
      currentKeywords: '',
      postResMessage: '',
      validationMgs: '',
      currPage: 1
    });
  };

  /**
   * @param {string} str - A string param
   * @return {boolean} A Boolean value
   * @description Check if string/input is a number
   * @default {boolean} Return a Boolean value (false)
   *
   * @example
   *     isStringNaN("22");
   *      returns true
   */
  isStringNaN = str => {
    return Number.isNaN(parseInt(str, 10)) === true;
  };

  /**
   * @param {string} addressArr - An array of strings
   * @param {object} suffixMatch - object containing the address's suffix (st/ave,etc) if it was not found
   * @return {object} An object containing the validation result and error message
   * @description Validate address by checking required components of 
   * an address (street number, street name, street suffix)
   * @default {object} Object containing error details
   *
   * @example
   *     validateAddress(
   *      ["22", "gates", "street"],
   *      { abr: "ave", label: "Ave"}
   *     );
   *      returns {valid: true, error: ""}
   * 
   *     validateAddress(
   *      ["gates", "street"],
   *      { abr: "ave", label: "Ave"}
   *     );
   *      returns {valid: false, error: [
   *        "Address should start with the address number",
   *      ]}
   */
  validateAddress = (addressArr, _suffixMatch) => {
    let valid = true;
    let erroMg = [];

    if (addressArr[0].length < 1) {
      erroMg.push('Address length provided is insuffient, please make sure you provide a street#, street name and suffix (ie. Ave, St ...)');
      valid = false;
    }

    // TODO: Disabled 'start address with a street number' requirement by REQUEST
    // if (this.isStringNaN(addressArr[0])) {
    //   erroMg.push('Address should start with the address number');
    //   valid = false;
    // }
    
    // TODO: Disabled 'suffix' requirement by REQUEST
    // if (
    //   typeof _suffixMatch !== 'object'
    // ) {
    //   erroMg.push('No address suffix was provided (ie. Ave, St, etc)');
    //   valid = false;
    // } else if (!_suffixMatch.abbr || !_suffixMatch.label) {
    //   erroMg.push('Addess suffix provided does not match any we support');
    //   valid = false;
    // }

    return {valid, error: erroMg};
  }

  /**
   * @param {string} addressArr - An array of strings
   * @return {objec} False or a key/value pair matching the index of the matching value found
   * @description Use regex to find a match for apt/unit string variations in address array of strings
   * @default false
   *
   * @example
   *     findAptUnitInAddress(["22", "gates", "street", "apt", "3"]);
   *      returns {index: 3, value: "apt"}
   * 
   *     findAptUnitInAddress(["22", "gates", "street"]);
   *      returns {index: -1, value: "", []}
   */
  findAptUnitInAddress = addressArr => {
    const { aptUnitLabels } = this.state;
    let address_arr = addressArr.map(x => x);
    let retObj = {
      index: -1,
      value: '',
      unit: '',
      unitIndex: -1,
      address_array: addressArr,
    };

    const index = addressArr.findIndex(element => {
      return aptUnitLabels.find(elem => {
        const elementLCase = element.toLocaleLowerCase();
        const elemLCase = elem.toLowerCase();
        return elemLCase === element.toLowerCase() || elementLCase.indexOf(elemLCase) > -1
      });
    });

    if (index > -1) {
      retObj.index = index;
      retObj.value = addressArr[index] ? addressArr[index].replace(/\d+/g, '') : '';

      const isAptUnitNull = addressArr[retObj.index].match(/\d+/g);

      if (isAptUnitNull) {
        const aptUnitNum = isAptUnitNull[0];

        address_arr.splice(retObj.index, 1, retObj.value);

        if (retObj.index+1 === address_arr.length) {
          address_arr.push(aptUnitNum);
        } else {
          address_arr.splice(index, 0, aptUnitNum);
        }

        retObj.unitIndex = retObj.index+1;
        retObj.unit = aptUnitNum;
      } else {
        const aptUnitIndex = retObj.index+1;

        if (
          addressArr[aptUnitIndex] &&
          this.isStringNaN(addressArr[aptUnitIndex]) === false
        ) {
          retObj.unitIndex = aptUnitIndex;
          retObj.unit = addressArr[aptUnitIndex];
        }
      }

      retObj.address_array = address_arr;
    }

    return retObj;
  }

  /**
   * @param {string} addressArr - An array of strings
   * @return {object} Either a boolean (false) if request not found
   * or an object denoting index/value for the match found
   * @description Check if string/input contains a string matching apt/unit
   *
   * @examples
   *     addressHasAptUnit(["22", "gates", "street", "apt", "3"]);
   *      return {index: 3, value: "apt"}
   *     
   *     addressHasAptUnit(["22", "gates", "street"]);
   *      return false
   */
  addressHasAptUnit = addressArr => {
    const aptUnitInAddress = this.findAptUnitInAddress(addressArr);

    return {
      label: aptUnitInAddress.value,
      labelIndex: aptUnitInAddress.index,
      unit: aptUnitInAddress.unit,
      unitIndex: aptUnitInAddress.unitIndex,
      valid: aptUnitInAddress.index > -1 || aptUnitInAddress.unitIndex > -1,
      address_array: aptUnitInAddress.address_array
    }
  }

  /**
   * @param {array} addressArr - An array of strings
   * @param {object} qryParams - state query options
   * @return {object} object containing constructed query url (API) and validation result
   * @description Get the query and validation for the corresponding address
   * @default {object} Object containing query string and validation result
   *
   * @examples
   *     getAddressQryStr(
   *      ["22", "gates", "street", "apt", "3"],
   *      
   *     );
   *      return {url: "qryUrl", validation: {isValidAddress}}
   */
  getAddressQryStr = (
    addressArr,
    qryParams,
  ) => {
    let qryUrl = '';

    const addressHasAptUnit = this.addressHasAptUnit(addressArr);
    let addrArrSansUnit = addressArr;

    if (addressHasAptUnit.valid && addressHasAptUnit.unitIndex > -1) {
      addrArrSansUnit = addressHasAptUnit.address_array;
      
      // Remove Apt/Unit indexes from the address array so it can be used in suffix validation
      [addressHasAptUnit.labelIndex, addressHasAptUnit.unitIndex]
        .sort((a, b) => b - a).forEach(currInstance => {
        addrArrSansUnit.splice(currInstance, 1);
      });
    }
    
    let getMatchingSuffixObj = this.state.st_suffix.find(obj => {
      const lastObj = addrArrSansUnit[addrArrSansUnit.length-1];

      if (obj.abbr && obj.label && lastObj && lastObj.toLocaleLowerCase()) {
        return obj.abbr.toLowerCase() === lastObj.toLocaleLowerCase() || obj.label.toLowerCase() === lastObj.toLocaleLowerCase();
      } else {
        return false;
      }
    });

    const sqlParams = qryParams.sql2.filters[this.state.searchByFilter];
    const isValidAddress = this.validateAddress(addrArrSansUnit, getMatchingSuffixObj);

    if (isValidAddress.valid) {
      let street_suffix = '';
      let addrArrSansUnitAndSuffix = addrArrSansUnit;

      if (getMatchingSuffixObj && getMatchingSuffixObj.abbr) {
        street_suffix = sqlParams.street_suffix.replace('__value__', `${getMatchingSuffixObj.abbr}`);

        addrArrSansUnitAndSuffix = JSON.parse(JSON.stringify(addrArrSansUnit));
        addrArrSansUnitAndSuffix.splice(addrArrSansUnit.indexOf(getMatchingSuffixObj.abbr), 1);
      }

      const isAddressSanUnitSuffixRoman = Object.keys (
        this.state.romanNumConversion
      ).find (
        key => key === addrArrSansUnitAndSuffix.join (' ').toLocaleLowerCase ()
      );
      const romanNum = isAddressSanUnitSuffixRoman
        ? this.state.romanNumConversion[isAddressSanUnitSuffixRoman]
        : undefined;
      const street_num = sqlParams.street_number.replace (
        '__value__',
        addrArrSansUnit[0]
      );
      console.log('isStringNaN: ', this.isStringNaN(addrArrSansUnit[0]), ' | romanNum: ', romanNum);
      let street_number =
        this.isStringNaN(addrArrSansUnit[0]) === true
          ? ``
          : `${street_num}`;
      if (
        this.isStringNaN(addrArrSansUnit[0]) === false &&
        romanNum !== 'undefined' &&
        typeof romanNum === 'string'
      ) {
        street_number = ``;
      }
      console.log('romanNum: ', romanNum);

      let addrArrSansUnitAndSuffix_trimmed = romanNum
        ? romanNum.trim ().split ()
        : addrArrSansUnitAndSuffix
            .join (' ')
            .replace (/[0-9]/g, '')
            .trim ()
            .split (' ');

      let street_name_only =
        addrArrSansUnitAndSuffix_trimmed.length > 1
          ? encodeURIComponent(
              addrArrSansUnitAndSuffix_trimmed
                // .slice(0, addrArrSansUnit.length - 1)
                .join(" ")
                .replace(/[0-9]/g, "")
                .trim()
            )
          : encodeURIComponent(addrArrSansUnitAndSuffix_trimmed[0]);

      const street_name = sqlParams.street_name_only.replace (
        '__value__',
        street_name_only
      );
      const sort = qryParams.sql2.sort.replace ('__value__', 'street_name');

      if (addressHasAptUnit.valid && addressHasAptUnit.unitIndex > -1) {
        const apt_unit = sqlParams.apt_unit.replace (
          '__value__',
          `${addressHasAptUnit.unit}`
        );
        qryUrl = `${qryParams.sql2.base}${street_number}${street_name}${street_suffix}${apt_unit}${sort}`;
      } else {
        qryUrl = `${qryParams.sql2.base}${street_number}${street_name}${street_suffix}${sort}`;
      }
    }

    return {url: qryUrl, validation: isValidAddress};
  };

  getOwnerQry = (qryParams, addressQuery) => {
    let qryStr = '';

    if (addressQuery.length > 2)
      qryStr = `${qryParams.base}${qryParams.sql.pre}${qryParams.sql.filters[this.state.searchByFilter].pre}${addressQuery}${qryParams.sql.filters[this.state.searchByFilter].post}`;
    return qryStr;
  };

  getParselIdQry = addressQuery => {
    const qryParams = this.state.queryStr;
    let qryUrl = '';

    if (/^[0-9]+$/.test(addressQuery) === true) {
      const filterUnparsed = `${qryParams.sql2.filters[this.state.searchByFilter][0]}`;
      qryUrl = `${qryParams.sql2.base}${filterUnparsed.replace('__value__', addressQuery)}`
      this.setState({
        postResMessage: ''
      });
    } else {
      this.setState({
        validationMgs: (
          <ul>
            <li>Please enter a numeric parcel/property ID.</li>
          </ul>
        )
      });
    }

    return qryUrl;
  };

  lookupAddress = () => {
    let qryUrl = '';
    let qryParams = this.state.queryStr;
    let addressStr = this.state.currentKeywords.trim();
    let addressQuery = addressStr ? encodeURI(addressStr.toUpperCase()) : '';
    const currSearchFilterObj = this.state.searchFilters[this.state.searchByFilter];

    switch(currSearchFilterObj.value) {
      case "address":
        addressStr = decodeURIComponent(addressQuery);
        const addressArr = addressStr.split(' ');
        const validate = this.getAddressQryStr(addressArr, qryParams);
        qryUrl = validate.url;

        if (!validate.validation.valid && validate.validation.error.length > 0) {
          this.setState({
            validationMgs: (
              <ul>
                {validate.validation.error.map((errorTxt, i) => {
                  return <li key={i}>{errorTxt}</li>
                })}
              </ul>
            )
          });
        }
        break;
      case "id":
        qryUrl = this.getParselIdQry(addressQuery);
        break;
      case "owner":
        addressQuery = encodeURI(addressStr.split(' ').reverse().join(' ').toUpperCase());
        qryUrl = this.getOwnerQry(qryParams, addressQuery);
        break;
    }

    if (qryUrl.length < 1) {
      this.setState({
        isLoading: false,
        itemsLookup: [],
        postResMessage: "No results were found."
      });
    } else {
      fetch (
        qryUrl, {method: 'POST', redirect: 'follow'},
      )
        .then(res => res.json())
        .then(
          result => {
            const postResMessage = result.length > 0 ? "" : "No results were found.";
            
            if (result.length > 0) {
              this.setState({
                isLoading: false,
                itemsLookup: result,
                postResMessage,
                validationMgs: ''
              });
            } else {
              this.setState({
                isLoading: false,
                itemsLookup: [],
                postResMessage,
                validationMgs: ''
              });
            }
          },
          // Note: it's important to handle errors here
          // instead of a catch() block so that we don't swallow
          // exceptions from actual bugs in components.
          error => {
            this.setState({
              isLoading: false,
              error
            });
          }
        );
    }
  };

  // Array Chunking
  chunkArray = (array, size) => {
    if(array.length <= size){
        return [array]
    }
    return [array.slice(0,size), ...this.chunkArray(array.slice(size), size)]
  }

  capitalizeStr = str => {
    return str.toLocaleLowerCase().replace(/\b\w/g, l => l.toUpperCase());
  }

  resultsMarkupFunc = (submittedKeywords, itemsLookupArray) => {
    let resultsMarkup = [];
    let resultItem;

    const capitalizeStr = str => {
      return str.toLocaleLowerCase().replace(/\b\w/g, l => l.toUpperCase());
    }

    if (submittedKeywords && itemsLookupArray && itemsLookupArray.length > 0) {
      for (const [index, value] of itemsLookupArray.entries()) {
        const currItem = itemsLookupArray[index];
        const owner = capitalizeStr(currItem.owner);
        const aptUnitStr = currItem.apt_unit.length > 0 ? ` Apt/Unit ${currItem.apt_unit}` : ``;
        let mail_address = capitalizeStr(`${currItem.street_number} ${currItem.street_name}`);
        mail_address = `${mail_address}${aptUnitStr}`;
        
        resultItem = (
          <a
            className="search-result"
            tabIndex='0'
            style={{ cursor: "pointer" }}
            key={index}
            href={`assessing-online/${currItem.parcel_id}`}
          >
            <li className="address-item rows">
              <div className="desktop">
                <div className="prop-value column-property">
                  {mail_address}
                </div>
                <div className="prop-value column-owner">
                  {owner}
                </div>
                <div className="prop-value column-parcel">
                  {currItem.parcel_id}
                </div>
                <div className="prop-value column-value">
                  ${currItem.total_value}
                </div>
              </div>

              <div className="mobile">
                <div className="left-col">
                  <div className="prop-value column-property">
                    {mail_address}
                  </div>
                  <div className="prop-value column-owner">
                    {owner}
                  </div>
                  <div className="prop-value column-parcel">
                    {currItem.parcel_id}
                  </div>
                </div>

                <div className="right-col">
                  <div className="prop-value column-value">
                    ${currItem.total_value}
                  </div>
                </div>
              </div>
            </li>
          </a>
        );
        resultsMarkup.push(resultItem);
      }
    }

    return resultsMarkup;
  };

  render () {
    const {
      currPage,
      pageMaxCount,
      itemsLookup,
      submittedKeywords,
      searchByFilter,
      searchFilters,
      currentKeywords,
    } = this.state;

    // Set and retreieve lookup items
    const chunckedItems = this.chunkArray(itemsLookup, pageMaxCount);
    let itemsLookupArray = chunckedItems.slice(0, currPage).flat();
    
    const resultsMarkup = this.resultsMarkupFunc(submittedKeywords, itemsLookupArray);

    const renderListHeaders = () => {
      let retElem = '';
      if (resultsMarkup.length > 0) {
        retElem = (
          <li className="header">
            <div className="header-desktop">
              <div className="header-label">Property</div>
              <div className="header-label">Owner</div>
              <div className="header-label">Parcel ID</div>
              <div className="header-label">Value</div>
            </div>

            <div className="header-mobile">
              <div className="header-label">Property</div>
              <div className="header-label">Value</div>
            </div>
          </li>
        );
      }
      return retElem;
    };

    const loadMoreElem = () => {
      let elem = '';
      const currCount = (currPage * pageMaxCount);
      const showingCurrent = currCount >= itemsLookup.length ? itemsLookup.length : currCount;

      if (itemsLookup.length > pageMaxCount) {
        elem = (
          <div>
            <div className="shown-label">
              Showing: {showingCurrent} out of {itemsLookup.length} results
            </div>

            {currPage < chunckedItems.length && (
              <div className="load-more">
                <button class="btn" onClick={this.handleLoadMoreResults}>Load More</button>
              </div>
            )}
          </div>
        );
      }

      return elem;
    };

    const resultsMessage = () => {
      const {validationMgs, postResMessage} = this.state;
      let ret = '';

      if (typeof validationMgs === 'object') {
        ret = (
          <div className="supporting-text">
            <label className="not-found">The Following issue were found:</label>
            {validationMgs}
          </div>
        );
      } else {
        if (postResMessage.length > 0) {
          ret = (
            <div className="supporting-text">
              <label className="not-found">{postResMessage}</label>
            </div>
          );
        }
      }

      return ret;
    };

    const getInstructionsSamplesMarkup = arr => {
      let retElem = [];

      arr.map((entry, i) => {
        if (typeof entry === 'object' && entry.length > 0) {
          retElem.push(
            <li className="no-list-style" key={i}>
              {getInstructionsSamplesMarkup(entry)}
            </li>
          );
        } else {
          retElem.push(<li key={i}>{entry}</li>);
        }
      })

      return retElem.length > 0 ? (<ul>{retElem}</ul>) : retElem;
    };

    const getInstructions = filter => {
      const retMarkup = (
        <div className="instructions">
          <label className="how-to-search">How to Search:</label>

          <div className="filter-by-desc">
            {filter.instructions}
            {filter.examples && getInstructionsSamplesMarkup(filter.examples)}
          </div>
        </div>
      );

      if (itemsLookup.length < 1 && !submittedKeywords) {
        return retMarkup;
      }

      if (itemsLookup.length > 0 && submittedKeywords === false) {
        return retMarkup;
      }

      return (<div />);
    };

    return (
      <div className="mnl">
        <SearchFilters
          searchByFilter={searchByFilter}
          searchFilters={searchFilters}
          onChange={this.handleChangeSearchFilterHandler}
        />
        
        <Search
          handleKeywordChange={this.handleKeywordChange}
          handleKeywordSubmit={this.handleKeywordSubmit}
          placeholder={searchFilters[searchByFilter].placeholder}
          searchClass="sf-i-f"
          value={currentKeywords}
        />

        <div className="mnl-mod">
          {this.state.isLoading ? (
            <div className="supporting-text">Loading ... </div>
          ) : (
            <div>
              <ul className="results-list">
                {renderListHeaders()}
                {resultsMarkup}
              </ul>
            </div>
          )}

          {submittedKeywords === true && resultsMessage()}
          
          {loadMoreElem()}
        </div>
        
        {getInstructions(searchFilters[searchByFilter])}
      </div>
    );
  }
}

ReactDOM.render(<MNL />,
  document.getElementById("web-app")
);
