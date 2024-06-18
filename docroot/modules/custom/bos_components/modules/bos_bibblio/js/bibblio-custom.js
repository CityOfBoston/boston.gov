/**
 * @file
 * Script to run Bibblio suggested content.
 */

// Polyfill for IE11.
Array.prototype.findIndex =
  Array.prototype.findIndex ||
  function (callback) {
    if (this === null) {
      throw new TypeError(
        "Array.prototype.findIndex called on null or undefined"
      );
    }
    else if (typeof callback !== "function") {
      throw new TypeError("callback must be a function");
    }
    var list = Object(this);
    // Makes sures is always has an positive integer as length.
    var length = list.length >>> 0;
    var thisArg = arguments[1];
    for (var i = 0; i < length; i++) {
      if (callback.call(thisArg, list[i], i, list)) {
        return i;
      }
    }
    return -1;
  };


// Get curernt domain.
var getDomain = window.location.host;

// Get node id from module vars which is used for Bibblio "cusotmUniqueIdentifier" value.
var nodeId = drupalSettings.bos_bibblio.bos_bibblio_js.node_id;

// Set vars for content hrefs.
const pageURL = window.location.pathname;
const siteLocation = "https://boston.gov";

var checkBadDesc = function (word) {
  return new RegExp("back to top", "i").test(word);
};

// Populate HTML container element with recommendation.
var populateHTML = function (bibContent) {
  var listItem = "";
  var listLength = 0;
  jQuery(bibContent).each(function (index, value) {
    if (listLength > 2) {
      return false;
    }

    let bibFields = value.fields; 
    let bibName = bibFields.name;
    let bibUrl = bibFields.url;
    let bibDesc = bibFields.description;
    if (checkBadDesc(bibDesc) === false && bibDesc !== "") {
      listItem +=
        '<a class= "cd g--4 g--4--sl m-t500 bibblio" bibblio-title="' +
        bibName +
        '" href="' +
        bibUrl +
        '"><div class="cd-c"><div class="cd-t">' +
        bibName +
        '</div><div class="cd-d">' +
        bibDesc +
        "</div></div></a>";
      listLength++;
    }
  });

  if (listLength > 0) {
    jQuery("#bibblio-custom div.g").append(listItem);
    jQuery(".cd-c").css("borderTop", "none");
    jQuery(".bibblio-container").show();
  }
};

// Check and set JSON LD data vars.
let JSON_LD = false;
let bibData2JSON;
if (
  typeof jQuery('script[type="application/ld+json"]')[0] !==
    "undefined" &&
  jQuery('script[type="application/ld+json"]')[0] !== null
) {
  let getJSON_LD = jQuery('script[type="application/ld+json"]')[0].innerHTML;
  getJSON_LD = JSON.parse(getJSON_LD);
  getJSON_LD = getJSON_LD["@graph"][0];

  // Convert JSON LD data into object.
  bibData2JSON = {
    fields: {
      url: siteLocation + pageURL,
      name: getJSON_LD.name,
      text: getJSON_LD.description,
      description: getJSON_LD.description,
      customUniqueIdentifier: nodeId,
      //customCatalogueId: 'Default 2022',
      catalogueId: "5ced1fd2-1a8f-4398-978e-07089effa336"
    }
  };
  JSON_LD = true;
}

// Check if page has existing recommendations.
var firstCheck = function () {
  jQuery.ajax({
    method: "GET",
    crossDomain: true,
    cache: false,
    url: "https://api.bibblio.org/v1/recommendations",
    contentType: "application/json",
    headers: {
      Authorization: "Bearer 852cf94f-5b38-4805-8b7b-a50c5a78609b"
    },
    data: {
      customUniqueIdentifier: nodeId,
      fields: "name,image,url,datePublished,description,keywords",
      limit: "6",
      catalogueId: "5ced1fd2-1a8f-4398-978e-07089effa336"
    },
    success: function (res) {
      if (jQuery("body").hasClass("node-type-how-to")) {
        let bibContent = res.results;
        populateHTML(bibContent);
        //console.log('found: ' + nodeId)
      }
    },
    error: function (res) {
      if (res.status == 404 && JSON_LD == true) {
        ingestItem();
        //console.log('not found - sent for ingest: ' + nodeId)
      }
      if (res.status == 412) {
        getItemExisting();
        //console.log('other error - get existing: ' + nodeId)
      }
      
    }
  });
};

// Get content item data from other group / catalogue.
var getItemExisting = function () {
  jQuery.ajax({
    method: "GET",
    crossDomain: true,
    cache: false,
    url: "https://api.bibblio.org/v1/recommendations",
    data: {
      customUniqueIdentifier: nodeId,
      fields: "name",
      limit: "1"
    },
    contentType: "application/json",
    headers: {
      Authorization: "Bearer 852cf94f-5b38-4805-8b7b-a50c5a78609b"
    },
    success: function (res) {
      const itemId = res._links.sourceContentItem.id;
      getItemData(itemId);
    },
    error: function (res) {
      console.log("Error getting existing recommendations.");
    }
  });
};

// Get item content specific value contentItemId of existing item to prep for update.
var getItemData = function (item_id) {
  let bibData = {};
  bibData["operation"] = "get";
  bibData["contentItemId"] = item_id;
  jQuery.ajax({
    method: "POST",
    url: "https://" + getDomain + "/rest/bibblio",
    contentType: "application/json",
    data: JSON.stringify(bibData),
    success: function (res) {
      resJSON = JSON.parse(res);
      if (resJSON.status == 200) {
        reIngestItem(resJSON.response);
      }
      else {
        console.log("Error ingesting item to Bibblio. " + resJSON.response);
      }
    },
    error: function (res) {
      resJSON = JSON.parse(res);
      console.log("Error ingesting item to Bibblio. " + resJSON.response);
    }
  });
};

// Ingest for first time and associate with catalogue ID.
var ingestItem = function () {
  bibData2JSON["operation"] = "create";
  jQuery.ajax({
    method: "POST",
    url: "https://" + getDomain + "/rest/bibblio",
    contentType: "application/json",
    data: JSON.stringify(bibData2JSON),
    success: function (res) {
      console.log('success: ' + JSON.stringify(res));
      resJSON = JSON.parse(res);
      if (resJSON.status == 200 || resJSON.status == 201) {
        console.log("success initial ingest");
      }
      else {
        console.log("error initial ingest: " + resJSON.response.errors);
      }
    },
    error: function (res) {
      resJSON = JSON.parse(res);
      console.log("error initial ingest: " + resJSON.response);
    }
  });
};

// Re-ingest and associate with catalogue ID.
var reIngestItem = function (res) {
  bibData2JSON["operation"] = "update";
  bibData2JSON["contentItemId"] = res.contentItemId;
  jQuery.ajax({
    method: "POST",
    contentType: "application/json",
    url: "https://" + getDomain + "/rest/bibblio",
    data: JSON.stringify(bibData2JSON),
    success: function (res) {
      resJSON = JSON.parse(res);
      if (resJSON.status == 200) {
        console.log("success re-ingest");
      }
      else {
        console.log("error re-ingest : " + resJSON.response.message);
      }
    },
    error: function (res) {
      resJSON = JSON.parse(res);
      console.log("error re-ingest : " + resJSON.response.message);
    }
  });
};
jQuery(window).on("load", function () {
  firstCheck();
});
